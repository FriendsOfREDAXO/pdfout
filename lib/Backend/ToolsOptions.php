<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\PdfOut\Backend;

use FriendsOfRedaxo\PdfOut\Permission;
use InvalidArgumentException;

/**
 * Eingaben der Seite „Werkzeuge“ – geprüft und normalisiert.
 *
 * Wird aus den POST-Daten des Formulars erzeugt (siehe fromArray()); alle Werte sind danach
 * auf erlaubte Werte beschränkt.
 *
 * @internal
 */
final class ToolsOptions
{
    public const STAMP_PAGES = ['all' => 'alle Seiten', 'first' => 'erste Seite', 'last' => 'letzte Seite', 'custom' => 'Auswahl …'];

    public const NUMBER_POSITIONS = [
        'bottom-center' => 'unten Mitte',
        'bottom-right' => 'unten rechts',
        'bottom-left' => 'unten links',
        'top-center' => 'oben Mitte',
        'top-right' => 'oben rechts',
        'top-left' => 'oben links',
    ];

    public const SIGNATURE_POSITIONS = ['bottom-right' => 'unten rechts (letzte Seite)', 'bottom-left' => 'unten links (letzte Seite)'];

    public const OUTPUT_DOWNLOAD = 'download';
    public const OUTPUT_MEDIA = 'media';

    /**
     * @param list<string> $media Dateinamen aus dem Medienpool in Reihenfolge
     * @param list<Permission> $allow
     */
    public function __construct(
        public readonly array $media = [],
        public readonly string $pages = '',
        public readonly string $stampText = '',
        public readonly float $stampOpacity = 0.15,
        public readonly string $stampPages = 'all',
        public readonly bool $numbers = false,
        public readonly string $numbersFormat = 'Seite {page} von {pages}',
        public readonly string $numbersPosition = 'bottom-center',
        public readonly bool $numbersSkipFirst = false,
        public readonly string $metaTitle = '',
        public readonly string $metaAuthor = '',
        public readonly string $metaSubject = '',
        public readonly bool $sign = false,
        public readonly string $signCertificate = '',
        public readonly string $signPassword = '',
        public readonly string $signName = '',
        public readonly string $signReason = '',
        public readonly string $signLocation = '',
        public readonly bool $signVisible = false,
        public readonly string $signPosition = 'bottom-right',
        public readonly bool $protect = false,
        public readonly string $userPassword = '',
        public readonly string $ownerPassword = '',
        public readonly array $allow = [],
        public readonly string $output = self::OUTPUT_DOWNLOAD,
        public readonly string $filename = '',
        public readonly int $categoryId = 0,
    ) {
    }

    /**
     * aus Formulardaten (Feldnamen wie im Formular der Seite „Werkzeuge“)
     *
     * @param array<mixed> $input
     * @throws InvalidArgumentException bei ungültigen Angaben
     */
    public static function fromArray(array $input): self
    {
        $string = static fn (string $key): string => isset($input[$key]) && is_scalar($input[$key]) ? trim((string) $input[$key]) : '';
        $bool = static fn (string $key): bool => '' !== $string($key) && '0' !== $string($key);
        $choice = static function (string $key, array $allowed, string $default) use ($string): string {
            $value = $string($key);
            return array_key_exists($value, $allowed) ? $value : $default;
        };

        $media = [];
        foreach (is_array($input['media'] ?? null) ? $input['media'] : [] as $name) {
            $name = is_scalar($name) ? trim((string) $name) : '';
            if ('' !== $name) {
                $media[] = $name;
            }
        }

        $stampPages = $choice('stamp_pages', self::STAMP_PAGES, 'all');
        if ('custom' === $stampPages) {
            $stampPages = $string('stamp_pages_custom');
            if ('' === $stampPages) {
                throw new InvalidArgumentException('Stempel: Seitenauswahl angeben (z. B. „1-3“) oder „alle Seiten“ wählen.');
            }
        }
        $opacity = (int) $string('stamp_opacity');
        $opacity = $opacity > 0 ? min(100, $opacity) : 15;

        $allow = [];
        foreach (is_array($input['allow'] ?? null) ? $input['allow'] : [] as $value) {
            if (is_string($value) && null !== ($permission = Permission::tryFrom($value))) {
                $allow[] = $permission;
            }
        }

        $format = $string('numbers_format');

        return new self(
            media: $media,
            pages: $string('pages'),
            stampText: $string('stamp_text'),
            stampOpacity: $opacity / 100,
            stampPages: $stampPages,
            numbers: $bool('numbers'),
            numbersFormat: '' !== $format ? $format : 'Seite {page} von {pages}',
            numbersPosition: $choice('numbers_position', self::NUMBER_POSITIONS, 'bottom-center'),
            numbersSkipFirst: $bool('numbers_skip_first'),
            metaTitle: $string('meta_title'),
            metaAuthor: $string('meta_author'),
            metaSubject: $string('meta_subject'),
            sign: $bool('sign'),
            signCertificate: $string('sign_certificate'),
            // Passwörter nicht trimmen
            signPassword: is_string($input['sign_password'] ?? null) ? $input['sign_password'] : '',
            signName: $string('sign_name'),
            signReason: $string('sign_reason'),
            signLocation: $string('sign_location'),
            signVisible: $bool('sign_visible'),
            signPosition: $choice('sign_position', self::SIGNATURE_POSITIONS, 'bottom-right'),
            protect: $bool('protect'),
            userPassword: is_string($input['user_password'] ?? null) ? $input['user_password'] : '',
            ownerPassword: is_string($input['owner_password'] ?? null) ? $input['owner_password'] : '',
            allow: Permission::list($allow),
            output: self::OUTPUT_MEDIA === $string('output') ? self::OUTPUT_MEDIA : self::OUTPUT_DOWNLOAD,
            filename: $string('filename'),
            categoryId: max(0, (int) $string('category_id')),
        );
    }

    /** wird das PDF bearbeitet (sonst unverändert durchgereicht, sofern nur eine Quelle) */
    public function hasSteps(): bool
    {
        return '' !== $this->pages || '' !== $this->stampText || $this->numbers || $this->hasMetadata() || $this->sign || $this->protect;
    }

    public function hasMetadata(): bool
    {
        return '' !== $this->metaTitle || '' !== $this->metaAuthor || '' !== $this->metaSubject;
    }
}
