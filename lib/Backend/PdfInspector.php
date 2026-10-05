<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\PdfOut\Backend;

use FriendsOfRedaxo\PdfOut\Poppler;
use InvalidArgumentException;
use RuntimeException;
use rex_dir;
use rex_path;

/**
 * Auswertung für die Seite „Prüfen“: Metadaten, Signaturen, Text und Vorschau über Poppler.
 *
 * @internal
 */
final class PdfInspector
{
    /** Länge der Textvorschau in Zeichen */
    public const TEXT_PREVIEW = 2000;

    /** Felder von pdfinfo mit deutscher Beschriftung, in Anzeigereihenfolge */
    public const INFO_LABELS = [
        'Title' => 'Titel',
        'Author' => 'Autor',
        'Subject' => 'Thema',
        'Keywords' => 'Stichwörter',
        'Creator' => 'Erstellt mit',
        'Producer' => 'PDF erzeugt von',
        'Pages' => 'Seiten',
        'Page size' => 'Seitenformat',
        'PDF version' => 'PDF-Version',
        'Encrypted' => 'Verschlüsselt',
        'Tagged' => 'Getaggt (barrierefrei strukturiert)',
        'CreationDate' => 'Erstellt am',
        'ModDate' => 'Geändert am',
        'File size' => 'Dateigröße',
    ];

    /**
     * Alles für die Anzeige ermitteln
     *
     * @return array{info: array<string, string>, signatures: list<array{field: string, signer: string, subject: string, signed_at: string, hash: string, type: string, valid: bool, status: string, certificate: string, certificate_trusted: bool, whole_document: bool}>, text: string, truncated: bool, thumbnail: string|null}
     * @throws InvalidArgumentException wenn das PDF ein (anderes) Passwort braucht
     * @throws RuntimeException bei Fehlern der Poppler-Programme
     */
    public static function inspect(string $file, string $password = ''): array
    {
        try {
            $info = Poppler::info($file, $password);
        } catch (RuntimeException $e) {
            if (1 === preg_match('/password/i', $e->getMessage())) {
                throw new InvalidArgumentException('' === $password
                    ? 'Das PDF ist mit einem Passwort geschützt – Passwort angeben.'
                    : 'Das Passwort passt nicht zu diesem PDF.', 0, $e);
            }
            throw $e;
        }

        $text = trim(Poppler::text($file, $password));
        $truncated = mb_strlen($text) > self::TEXT_PREVIEW;

        return [
            'info' => $info,
            'signatures' => Poppler::signatures($file, $password),
            'text' => $truncated ? mb_substr($text, 0, self::TEXT_PREVIEW) : $text,
            'truncated' => $truncated,
            'thumbnail' => self::thumbnail($file, $password),
        ];
    }

    /** Vorschau der ersten Seite als data-URI (PNG, 40 dpi); null, wenn sie sich nicht erzeugen lässt */
    public static function thumbnail(string $file, string $password = ''): ?string
    {
        $base = rex_path::addonCache('pdfout', 'verify_' . bin2hex(random_bytes(8)));
        rex_dir::create(dirname($base));
        $args = ['-png', '-r', '40', '-f', '1', '-l', '1', '-singlefile'];
        if ('' !== $password) {
            array_push($args, '-upw', $password);
        }
        array_push($args, $file, $base);
        try {
            Poppler::run('pdftoppm', $args);
            $png = is_file($base . '.png') ? (string) file_get_contents($base . '.png') : '';
            return '' !== $png ? 'data:image/png;base64,' . base64_encode($png) : null;
        } catch (RuntimeException) {
            return null;
        } finally {
            @unlink($base . '.png');
        }
    }

    /** PDF-Datum (pdfinfo, z. B. „Mon Oct  5 10:12:00 2026 CEST“) lesbar formatieren; unverändert, wenn nicht erkennbar */
    public static function formatDate(string $value): string
    {
        $value = trim($value);
        if ('' === $value) {
            return '';
        }
        $timestamp = strtotime($value);
        return false !== $timestamp ? date('d.m.Y H:i', $timestamp) : $value;
    }
}
