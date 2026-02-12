<?php

use FriendsOfRedaxo\PdfOut\PdfThumbnail;

/**
 * Media-Manager-Effekt: PDF-Thumbnail
 *
 * Erzeugt Vorschaubilder der ersten Seite von PDFs.
 * Verwendet poppler-utils (pdftoppm/pdftocairo) oder Ghostscript direkt,
 * um die ImageMagick-Policy-Einschränkung auf Ubuntu/Debian zu umgehen.
 *
 * Installation: apt install poppler-utils
 *
 * @package FriendsOfRedaxo\PdfOut
 */
class rex_effect_pdf_thumbnail extends rex_effect_abstract
{
    private const CONVERT_TO = [
        'jpg' => [
            'ext' => 'jpg',
            'content-type' => 'image/jpeg',
        ],
        'png' => [
            'ext' => 'png',
            'content-type' => 'image/png',
        ],
    ];

    private const DENSITIES = [72, 100, 150, 200, 300];
    private const DENSITY_DEFAULT = 150;
    private const QUALITY_DEFAULT = 85;
    private const CONVERT_TOS = ['jpg', 'png'];
    private const CONVERT_TO_DEFAULT = 'jpg';

    public function execute(): void
    {
        $inputFile = $this->media->getMediaPath();
        if ($inputFile === null) {
            return;
        }

        $ext = strtolower(rex_file::extension($inputFile));
        if ($ext !== 'pdf') {
            return;
        }

        $fromPath = realpath($inputFile);
        if ($fromPath === false || !file_exists($fromPath)) {
            return;
        }

        // Zielformat bestimmen
        $convertTo = self::CONVERT_TO[self::CONVERT_TO_DEFAULT];
        if (isset($this->params['convert_to']) && isset(self::CONVERT_TO[(string) $this->params['convert_to']])) {
            $convertTo = self::CONVERT_TO[(string) $this->params['convert_to']];
        }

        // Parameter auswerten
        $density = (int) ($this->params['density'] ?? self::DENSITY_DEFAULT);
        if (!in_array($density, self::DENSITIES, true)) {
            $density = self::DENSITY_DEFAULT;
        }

        $quality = (int) ($this->params['quality'] ?? self::QUALITY_DEFAULT);
        $quality = max(1, min(100, $quality));

        $page = max(1, (int) ($this->params['page'] ?? 1));
        $color = (string) ($this->params['color'] ?? 'ffffff');

        // PdfThumbnail verwenden
        $thumbnail = new PdfThumbnail();
        $thumbnail
            ->setFormat($convertTo['ext'])
            ->setDpi($density)
            ->setQuality($quality)
            ->setPage($page)
            ->setBackgroundColor($color)
            ->setCache(false); // Media Manager cached selbst

        // Thumbnail generieren
        $gdImage = $thumbnail->generateAsGdImage($fromPath);

        if ($gdImage instanceof \GdImage) {
            $this->media->setImage($gdImage);
            $this->media->setFormat($convertTo['ext']);
            $this->media->setHeader('Content-Type', $convertTo['content-type']);
            $this->media->refreshImageDimensions();
            return;
        }

        // Fallback: Dateipfad-basiert
        $imagePath = $thumbnail->generate($fromPath);
        if ($imagePath !== null && file_exists($imagePath)) {
            $this->media->setSourcePath($imagePath);
            $this->media->refreshImageDimensions();
            $this->media->setFormat($convertTo['ext']);
            $this->media->setHeader('Content-Type', $convertTo['content-type']);

            $filename = $this->media->getMediaFilename();
            $this->media->setMediaFilename($filename);

            // Temporäre Datei nach Request aufräumen
            register_shutdown_function(static function () use ($imagePath): void {
                rex_file::delete($imagePath);
            });
        }
    }

    public function getName(): string
    {
        return rex_i18n::msg('pdfout_effect_pdf_thumbnail');
    }

    /**
     * @return list<array{label: string, name: string, type: string, default?: mixed, options?: list<string|int>, prefix?: string, notice?: string}>
     */
    public function getParams(): array
    {
        // Status-Meldungen
        $status = PdfThumbnail::getStatus();
        $statusHtml = '';

        if ($status['available']) {
            $statusHtml = '<span style="color:green;">✓ ' . rex_escape($status['message']) . '</span>';
        } else {
            $statusHtml = '<span style="color:red;">✗ ' . rex_escape($status['message']) . '</span>';
        }

        // Alle verfügbaren Tools anzeigen
        $tools = PdfThumbnail::checkAvailableTools();
        $toolList = [];
        foreach ($tools as $toolName => $available) {
            $icon = $available ? '✓' : '✗';
            $toolList[] = $icon . ' ' . $toolName;
        }
        $statusHtml .= '<br><small>' . implode(' | ', $toolList) . '</small>';

        return [
            [
                'label' => rex_i18n::msg('pdfout_effect_pdf_thumbnail_status'),
                'name' => 'status_info',
                'type' => 'string',
                'default' => '',
                'prefix' => $statusHtml,
                'notice' => '',
            ],
            [
                'label' => rex_i18n::msg('pdfout_effect_pdf_thumbnail_convert_to'),
                'name' => 'convert_to',
                'type' => 'select',
                'options' => self::CONVERT_TOS,
                'default' => self::CONVERT_TO_DEFAULT,
                'notice' => rex_i18n::msg('pdfout_effect_pdf_thumbnail_convert_to_notice'),
            ],
            [
                'label' => rex_i18n::msg('pdfout_effect_pdf_thumbnail_density'),
                'name' => 'density',
                'type' => 'select',
                'options' => self::DENSITIES,
                'default' => self::DENSITY_DEFAULT,
                'notice' => rex_i18n::msg('pdfout_effect_pdf_thumbnail_density_notice'),
            ],
            [
                'label' => rex_i18n::msg('pdfout_effect_pdf_thumbnail_quality'),
                'name' => 'quality',
                'type' => 'int',
                'default' => self::QUALITY_DEFAULT,
                'notice' => rex_i18n::msg('pdfout_effect_pdf_thumbnail_quality_notice'),
            ],
            [
                'label' => rex_i18n::msg('pdfout_effect_pdf_thumbnail_page'),
                'name' => 'page',
                'type' => 'int',
                'default' => 1,
                'notice' => rex_i18n::msg('pdfout_effect_pdf_thumbnail_page_notice'),
            ],
            [
                'label' => rex_i18n::msg('pdfout_effect_pdf_thumbnail_color'),
                'name' => 'color',
                'type' => 'string',
                'default' => 'ffffff',
                'notice' => rex_i18n::msg('pdfout_effect_pdf_thumbnail_color_notice'),
            ],
        ];
    }
}
