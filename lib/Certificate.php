<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\PdfOut;

use InvalidArgumentException;
use rex_addon;

/**
 * Signatur-Zertifikat mit privatem Schlüssel.
 *
 * Wird einmal geladen und geprüft; tc-lib-pdf bekommt die PEM-Daten direkt (kein Dateizugriff
 * durch die Bibliothek nötig).
 */
final class Certificate
{
    /**
     * @param string $certificate Zertifikat (PEM)
     * @param string $privateKey privater Schlüssel (PEM, unverschlüsselt)
     * @param list<string> $chain weitere Zertifikate der Kette (PEM)
     */
    private function __construct(
        public readonly string $certificate,
        public readonly string $privateKey,
        public readonly array $chain = [],
    ) {
        if (false === openssl_x509_read($certificate)) {
            throw new InvalidArgumentException('Ungültiges Zertifikat.');
        }
        $key = openssl_pkey_get_private($privateKey);
        if (false === $key || !openssl_x509_check_private_key($certificate, $key)) {
            throw new InvalidArgumentException('Privater Schlüssel passt nicht zum Zertifikat.');
        }
    }

    /** PKCS#12-Datei (.p12/.pfx) */
    public static function fromP12(string $file, string $password = ''): self
    {
        $data = self::read($file);
        if (!openssl_pkcs12_read($data, $parts, $password)) {
            throw new InvalidArgumentException('Zertifikat lässt sich nicht öffnen – Passwort falsch oder Datei beschädigt: ' . basename($file));
        }
        return new self((string) $parts['cert'], (string) $parts['pkey'], array_values(array_map('strval', $parts['extracerts'] ?? [])));
    }

    /**
     * PEM-Dateien: Zertifikat und Schlüssel getrennt oder zusammen in einer Datei
     *
     * @param string|null $keyFile Datei mit privatem Schlüssel (leer = in der Zertifikatsdatei)
     */
    public static function fromPem(string $certificateFile, ?string $keyFile = null, string $password = ''): self
    {
        $pem = self::read($certificateFile);
        $keyPem = null !== $keyFile ? self::read($keyFile) : $pem;
        $key = openssl_pkey_get_private($keyPem, $password);
        if (false === $key || !openssl_pkey_export($key, $privateKey)) {
            throw new InvalidArgumentException('Privater Schlüssel lässt sich nicht lesen – Passwort falsch?');
        }
        preg_match_all('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $pem, $m);
        $certificates = $m[0];
        if ([] === $certificates) {
            throw new InvalidArgumentException('Kein Zertifikat in ' . basename($certificateFile));
        }
        return new self(array_shift($certificates), (string) $privateKey, $certificates);
    }

    /** Datei nach Endung laden (.p12/.pfx oder .pem/.crt) */
    public static function fromFile(string $file, string $password = ''): self
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'p12', 'pfx' => self::fromP12($file, $password),
            default => self::fromPem($file, null, $password),
        };
    }

    /**
     * Zertifikat aus dem Addon-Ordner data/certificates (Seite „Zertifikate“); leer = Standard aus den Einstellungen
     */
    public static function fromAddon(string $name = '', ?string $password = null): self
    {
        $addon = rex_addon::get('pdfout');
        $name = '' !== $name ? $name : (string) $addon->getConfig('default_certificate_selection', 'default.p12');
        if ('' === $name || str_contains($name, '/') || str_contains($name, '\\')) {
            throw new InvalidArgumentException('Ungültiger Zertifikatsname.');
        }
        return self::fromFile($addon->getDataPath('certificates/' . $name), $password ?? (string) $addon->getConfig('default_certificate_password', ''));
    }

    /** Name aus dem Zertifikat (CN) */
    public function commonName(): string
    {
        $info = openssl_x509_parse($this->certificate) ?: [];
        return (string) ($info['subject']['CN'] ?? '');
    }

    /** Ablaufdatum */
    public function validTo(): \DateTimeImmutable
    {
        $info = openssl_x509_parse($this->certificate) ?: [];
        return (new \DateTimeImmutable())->setTimestamp((int) ($info['validTo_time_t'] ?? 0));
    }

    /** weitere Zertifikate der Kette als PEM-Block (null = keine) */
    public function chainBundle(): ?string
    {
        return [] === $this->chain ? null : implode("\n", $this->chain);
    }

    private static function read(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new InvalidArgumentException('Zertifikatsdatei nicht gefunden: ' . basename($file));
        }
        return (string) file_get_contents($file);
    }
}
