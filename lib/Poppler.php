<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\PdfOut;

use RuntimeException;
use rex_addon;

/**
 * Anbindung an die poppler-utils (pdfinfo, pdfsig, pdftoppm, pdftotext).
 *
 * Programme werden ohne Shell aufgerufen (proc_open mit Argument-Liste) – Dateinamen und
 * Passwörter können also keine Befehle einschleusen. Der Ordner der Programme lässt sich in den
 * Einstellungen festlegen (poppler_path), sonst wird im PATH und in üblichen Ordnern gesucht.
 */
final class Poppler
{
    /** Programme, die pdfout voraussetzt */
    public const REQUIRED = ['pdfinfo', 'pdfsig', 'pdftoppm', 'pdftotext'];

    /** übliche Installationsorte (Linux-Pakete, Homebrew, MacPorts) */
    private const SEARCH_DIRS = ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin', '/opt/local/bin'];

    /** @var array<string, string|null> */
    private static array $binaries = [];

    public static function binary(string $tool): ?string
    {
        if (array_key_exists($tool, self::$binaries)) {
            return self::$binaries[$tool];
        }
        $dirs = [];
        $configured = trim((string) rex_addon::get('pdfout')->getConfig('poppler_path', ''));
        if ('' !== $configured) {
            $dirs[] = rtrim($configured, '/');
        }
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            if ('' !== $dir) {
                $dirs[] = rtrim($dir, '/');
            }
        }
        foreach ([...$dirs, ...self::SEARCH_DIRS] as $dir) {
            $path = $dir . '/' . $tool;
            if (@is_file($path) && @is_executable($path)) {
                return self::$binaries[$tool] = $path;
            }
        }
        return self::$binaries[$tool] = null;
    }

    public static function isAvailable(): bool
    {
        return [] === self::missing();
    }

    /** @return list<string> fehlende Programme */
    public static function missing(): array
    {
        return array_values(array_filter(self::REQUIRED, static fn (string $tool): bool => null === self::binary($tool)));
    }

    public static function version(): ?string
    {
        $result = self::tryRun('pdfinfo', ['-v']);
        if (null === $result) {
            return null;
        }
        return preg_match('/version\s+([\d.]+)/i', $result['stdout'] . $result['stderr'], $m) ? $m[1] : null;
    }

    /**
     * Metadaten eines PDFs (Seiten, Format, Verschlüsselung, PDF-Version …)
     *
     * @return array<string, string> z. B. ['Pages' => '3', 'Encrypted' => 'no', 'Page size' => '595 x 842 pts (A4)']
     */
    public static function info(string $file, string $password = ''): array
    {
        $args = '' !== $password ? ['-upw', $password, $file] : [$file];
        $result = self::run('pdfinfo', $args);
        $info = [];
        foreach (preg_split('/\R/', $result['stdout']) ?: [] as $line) {
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $m)) {
                $info[trim($m[1])] = trim($m[2]);
            }
        }
        return $info;
    }

    public static function pageCount(string $file, string $password = ''): int
    {
        return (int) (self::info($file, $password)['Pages'] ?? 0);
    }

    /**
     * Prüft alle Signaturen eines PDFs mit pdfsig.
     *
     * @return list<array{field: string, signer: string, subject: string, signed_at: string, hash: string, type: string, valid: bool, status: string, certificate: string, certificate_trusted: bool, whole_document: bool}>
     */
    public static function signatures(string $file, string $password = ''): array
    {
        $args = '' !== $password ? ['-upw', $password, $file] : [$file];
        $result = self::run('pdfsig', $args, allowExitCodes: [0, 1, 2, 3]);
        $output = $result['stdout'] . "\n" . $result['stderr'];

        $signatures = [];
        foreach (preg_split('/^Signature #\d+:\s*$/m', $output) ?: [] as $index => $block) {
            if (0 === $index) {
                continue; // Kopfzeile „Digital Signature Info of: …“
            }
            // [ \t] statt \s: ein leerer Wert (z. B. Feldname einer unsichtbaren Signatur) darf nicht die nächste Zeile übernehmen
            $value = static fn (string $label): string => preg_match('/^[ \t]*-[ \t]*' . preg_quote($label, '/') . ':[ \t]*(.*)$/mi', $block, $m) ? trim($m[1]) : '';
            $status = $value('Signature Validation');
            $certificate = $value('Certificate Validation');
            $signatures[] = [
                'field' => $value('Signature Field Name'),
                'signer' => $value('Signer Certificate Common Name'),
                'subject' => $value('Signer full Distinguished Name'),
                'signed_at' => $value('Signing Time'),
                'hash' => $value('Signing Hash Algorithm'),
                'type' => $value('Signature Type'),
                'valid' => 'Signature is Valid.' === $status,
                'status' => $status,
                'certificate' => $certificate,
                'certificate_trusted' => str_starts_with($certificate, 'Certificate is Trusted'),
                'whole_document' => str_contains($block, 'Total document signed'),
            ];
        }
        return $signatures;
    }

    /** Text eines PDFs (z. B. für Suche oder Tests) */
    public static function text(string $file, string $password = '', bool $layout = false): string
    {
        $args = [];
        if ('' !== $password) {
            array_push($args, '-upw', $password);
        }
        if ($layout) {
            $args[] = '-layout';
        }
        array_push($args, $file, '-');
        return self::run('pdftotext', $args)['stdout'];
    }

    /**
     * Führt ein Poppler-Programm aus.
     *
     * @param list<string> $args
     * @param list<int> $allowExitCodes
     * @return array{stdout: string, stderr: string, code: int}
     */
    public static function run(string $tool, array $args, int $timeout = 60, array $allowExitCodes = [0]): array
    {
        $binary = self::binary($tool);
        if (null === $binary) {
            throw new RuntimeException(sprintf('Poppler-Programm „%s“ nicht gefunden. pdfout setzt die poppler-utils voraus (siehe Einstellungen).', $tool));
        }
        $process = proc_open([$binary, ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException(sprintf('„%s“ konnte nicht gestartet werden.', $tool));
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = $stderr = '';
        $deadline = microtime(true) + $timeout;
        do {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($process);
                throw new RuntimeException(sprintf('„%s“ hat das Zeitlimit von %d s überschritten.', $tool, $timeout));
            }
            usleep(10_000);
        } while (true);
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $code = (int) $status['exitcode'];

        if (!in_array($code, $allowExitCodes, true)) {
            throw new RuntimeException(sprintf('„%s“ meldet Fehler %d: %s', $tool, $code, trim($stderr) ?: trim($stdout)));
        }
        return ['stdout' => $stdout, 'stderr' => $stderr, 'code' => $code];
    }

    /**
     * @param list<string> $args
     * @return array{stdout: string, stderr: string, code: int}|null
     */
    private static function tryRun(string $tool, array $args): ?array
    {
        try {
            return self::run($tool, $args, 10, [0, 1, 99]);
        } catch (RuntimeException) {
            return null;
        }
    }
}
