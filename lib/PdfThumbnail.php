<?php

namespace FriendsOfRedaxo\PdfOut;

use rex;
use rex_addon;
use rex_dir;
use rex_file;
use rex_logger;
use rex_path;

/**
 * PdfThumbnail - Erzeugt Vorschaubilder (Thumbnails) von PDF-Dateien
 *
 * Löst das Problem, dass Ubuntu/Debian seit 2018 die ImageMagick-Policy
 * für PDF-Konvertierung via Ghostscript gesperrt hat.
 *
 * Verwendet alternative Tools in folgender Reihenfolge:
 * 1. pdftoppm (poppler-utils) - Empfohlen, nicht von ImageMagick-Policy betroffen
 * 2. pdftocairo (poppler-utils) - Alternative aus dem gleichen Paket
 * 3. gs (Ghostscript direkt) - Ghostscript ohne ImageMagick-Umweg
 * 4. Imagick PHP-Extension - Fallback, funktioniert evtl. trotz Policy
 *
 * Installation der Abhängigkeiten auf Ubuntu/Debian:
 *   apt install poppler-utils
 *
 * @package FriendsOfRedaxo\PdfOut
 */
class PdfThumbnail
{
    /** @var string Ausgabeformat (png|jpg) */
    private string $format = 'png';

    /** @var int JPEG-Qualität (1-100) */
    private int $quality = 85;

    /** @var int DPI-Auflösung für das Rendering */
    private int $dpi = 150;

    /** @var int Zu rendernde Seitennummer (1-basiert) */
    private int $page = 1;

    /** @var int Maximale Breite in Pixel (0 = keine Begrenzung) */
    private int $maxWidth = 0;

    /** @var int Maximale Höhe in Pixel (0 = keine Begrenzung) */
    private int $maxHeight = 0;

    /** @var string Hintergrundfarbe für transparente Bereiche (Hex ohne #) */
    private string $backgroundColor = 'ffffff';

    /** @var float Gamma-Korrektur (1.0 = keine, <1 dunkler, >1 heller) */
    private float $gamma = 1.0;

    /** @var bool sRGB ICC-Profil in das Ausgabebild einbetten */
    private bool $embedIccProfile = false;

    /** @var string|null Cache-Verzeichnis */
    private ?string $cacheDir = null;

    /** @var bool Cache aktiviert */
    private bool $cacheEnabled = true;

    /** @var string|null Erkanntes Tool für die Konvertierung */
    private static ?string $detectedTool = null;

    /**
     * Erstellt eine neue PdfThumbnail-Instanz
     */
    public function __construct()
    {
        $addon = rex_addon::get('pdfout');
        $this->cacheDir = $addon->getCachePath('thumbnails/');
        rex_dir::create($this->cacheDir);
    }

    /**
     * Setzt das Ausgabeformat
     *
     * @param string $format 'jpg' oder 'png'
     * @return self
     */
    public function setFormat(string $format): self
    {
        $format = strtolower($format);
        if (!in_array($format, ['jpg', 'jpeg', 'png'], true)) {
            $format = 'jpg';
        }
        $this->format = ($format === 'jpeg') ? 'jpg' : $format;
        return $this;
    }

    /**
     * Setzt die JPEG-Qualität
     *
     * @param int $quality Qualität 1-100
     * @return self
     */
    public function setQuality(int $quality): self
    {
        $this->quality = max(1, min(100, $quality));
        return $this;
    }

    /**
     * Setzt die DPI-Auflösung
     *
     * @param int $dpi DPI-Wert (typisch: 72, 100, 150, 200, 300)
     * @return self
     */
    public function setDpi(int $dpi): self
    {
        $this->dpi = max(36, min(600, $dpi));
        return $this;
    }

    /**
     * Setzt die zu rendernde Seitennummer
     *
     * @param int $page Seitennummer (1-basiert)
     * @return self
     */
    public function setPage(int $page): self
    {
        $this->page = max(1, $page);
        return $this;
    }

    /**
     * Setzt die maximale Breite des Thumbnails
     *
     * @param int $width Maximale Breite in Pixel (0 = keine Begrenzung)
     * @return self
     */
    public function setMaxWidth(int $width): self
    {
        $this->maxWidth = max(0, $width);
        return $this;
    }

    /**
     * Setzt die maximale Höhe des Thumbnails
     *
     * @param int $height Maximale Höhe in Pixel (0 = keine Begrenzung)
     * @return self
     */
    public function setMaxHeight(int $height): self
    {
        $this->maxHeight = max(0, $height);
        return $this;
    }

    /**
     * Setzt die Hintergrundfarbe
     *
     * @param string $color Hex-Farbcode ohne # (z.B. 'ffffff')
     * @return self
     */
    public function setBackgroundColor(string $color): self
    {
        $this->backgroundColor = ltrim($color, '#');
        return $this;
    }

    /**
     * Setzt die Gamma-Korrektur
     *
     * Werte > 1.0 hellen das Bild auf (empfohlen: 1.1–1.4 für PDF-Thumbnails),
     * Werte < 1.0 dunkeln ab, 1.0 = keine Korrektur.
     *
     * Hintergrund: PDF-Viewer wie macOS Preview nutzen Display-Farbmanagement
     * (z.B. Display P3), was dunkle Farben satter/heller erscheinen lässt.
     * Browser zeigen Thumbnails ohne dieses Mapping, daher wirken die Farben dunkler.
     *
     * @param float $gamma Gamma-Wert (0.5–2.0)
     * @return self
     */
    public function setGamma(float $gamma): self
    {
        $this->gamma = max(0.5, min(2.0, $gamma));
        return $this;
    }

    /**
     * Aktiviert/deaktiviert das Einbetten eines sRGB ICC-Profils
     *
     * Mit eingebettetem ICC-Profil können Browser und Bildprogramme
     * die Farben korrekt interpretieren. Benötigt die PHP-Extension Imagick.
     *
     * @param bool $embed true = ICC-Profil einbetten
     * @return self
     */
    public function setEmbedIccProfile(bool $embed): self
    {
        $this->embedIccProfile = $embed;
        return $this;
    }

    /**
     * Aktiviert oder deaktiviert den Cache
     *
     * @param bool $enabled Cache ein/aus
     * @return self
     */
    public function setCache(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        return $this;
    }

    /**
     * Setzt ein benutzerdefiniertes Cache-Verzeichnis
     *
     * @param string $dir Pfad zum Cache-Verzeichnis
     * @return self
     */
    public function setCacheDir(string $dir): self
    {
        $this->cacheDir = rtrim($dir, '/') . '/';
        rex_dir::create($this->cacheDir);
        return $this;
    }

    /**
     * Generiert ein Thumbnail-Bild einer PDF-Datei
     *
     * @param string $pdfPath Absoluter Pfad zur PDF-Datei
     * @return string|null Pfad zum generierten Thumbnail oder null bei Fehler
     */
    public function generate(string $pdfPath): ?string
    {
        if (!file_exists($pdfPath)) {
            rex_logger::factory()->warning('PdfThumbnail: PDF-Datei nicht gefunden: {path}', ['path' => $pdfPath]);
            return null;
        }

        $ext = strtolower(pathinfo($pdfPath, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return null;
        }

        // Cache prüfen
        $cacheKey = $this->getCacheKey($pdfPath);
        $cachePath = $this->cacheDir . $cacheKey . '.' . $this->format;

        if ($this->cacheEnabled && file_exists($cachePath)) {
            $pdfMtime = filemtime($pdfPath);
            $cacheMtime = filemtime($cachePath);
            if ($cacheMtime >= $pdfMtime) {
                return $cachePath;
            }
        }

        // Thumbnail generieren
        $outputPath = $this->renderPdfToImage($pdfPath);
        if ($outputPath === null) {
            return null;
        }

        // Nachbearbeitung: Resize falls nötig
        if ($this->maxWidth > 0 || $this->maxHeight > 0) {
            $outputPath = $this->resizeImage($outputPath);
        }

        // Gamma-Korrektur anwenden
        if (abs($this->gamma - 1.0) > 0.01) {
            $outputPath = $this->applyGammaCorrection($outputPath);
        }

        // ICC-Profil einbetten
        if ($this->embedIccProfile) {
            $outputPath = $this->embedSrgbIccProfile($outputPath);
        }

        // In Cache verschieben
        if ($this->cacheEnabled && $outputPath !== $cachePath) {
            if (copy($outputPath, $cachePath)) {
                @unlink($outputPath);
                return $cachePath;
            }
        }

        return $outputPath;
    }

    /**
     * Generiert ein Thumbnail und gibt es als GD-Resource zurück
     *
     * @param string $pdfPath Absoluter Pfad zur PDF-Datei
     * @return \GdImage|null GD-Image-Resource oder null bei Fehler
     */
    public function generateAsGdImage(string $pdfPath): ?\GdImage
    {
        $imagePath = $this->generate($pdfPath);
        if ($imagePath === null) {
            return null;
        }

        $image = match ($this->format) {
            'png' => @imagecreatefrompng($imagePath),
            default => @imagecreatefromjpeg($imagePath),
        };

        return $image instanceof \GdImage ? $image : null;
    }

    /**
     * Generiert ein Thumbnail und gibt den Binärinhalt zurück
     *
     * @param string $pdfPath Absoluter Pfad zur PDF-Datei
     * @return string|null Binärer Bildinhalt oder null bei Fehler
     */
    public function generateAsString(string $pdfPath): ?string
    {
        $imagePath = $this->generate($pdfPath);
        if ($imagePath === null) {
            return null;
        }

        $content = rex_file::get($imagePath);
        return $content !== null ? $content : null;
    }

    /**
     * Prüft welche Tools für die PDF-Konvertierung verfügbar sind
     *
     * @return array<string, bool> Assoziatives Array mit Verfügbarkeitsstatus
     */
    public static function checkAvailableTools(): array
    {
        return [
            'pdftoppm' => self::isToolAvailable('pdftoppm'),
            'pdftocairo' => self::isToolAvailable('pdftocairo'),
            'gs' => self::isToolAvailable('gs'),
            'imagick' => class_exists(\Imagick::class),
            'convert' => self::isToolAvailable('convert'),
        ];
    }

    /**
     * Gibt den Namen des erkannten/verwendeten Tools zurück
     *
     * @return string Toolname oder 'none'
     */
    public static function getDetectedTool(): string
    {
        if (self::$detectedTool !== null) {
            return self::$detectedTool;
        }

        if (self::isToolAvailable('pdftoppm')) {
            self::$detectedTool = 'pdftoppm';
        } elseif (self::isToolAvailable('pdftocairo')) {
            self::$detectedTool = 'pdftocairo';
        } elseif (self::isToolAvailable('gs')) {
            self::$detectedTool = 'gs';
        } elseif (class_exists(\Imagick::class)) {
            self::$detectedTool = 'imagick';
        } else {
            self::$detectedTool = 'none';
        }

        return self::$detectedTool;
    }

    /**
     * Gibt eine Status-Zusammenfassung für das Backend zurück
     *
     * @return array{tool: string, available: bool, message: string}
     */
    public static function getStatus(): array
    {
        $tool = self::getDetectedTool();

        return match ($tool) {
            'pdftoppm', 'pdftocairo' => [
                'tool' => $tool,
                'available' => true,
                'message' => $tool . ' (poppler-utils) verfügbar – empfohlene Methode, nicht von ImageMagick-Policy betroffen.',
            ],
            'gs' => [
                'tool' => 'gs',
                'available' => true,
                'message' => 'Ghostscript direkt verfügbar – funktioniert ohne ImageMagick.',
            ],
            'imagick' => [
                'tool' => 'imagick',
                'available' => true,
                'message' => 'PHP Imagick verfügbar – funktioniert evtl. nicht bei restriktiver ImageMagick-Policy.',
            ],
            default => [
                'tool' => 'none',
                'available' => false,
                'message' => 'Kein PDF-Konverter gefunden. Bitte poppler-utils installieren: apt install poppler-utils',
            ],
        };
    }

    /**
     * Leert den Thumbnail-Cache
     *
     * @return int Anzahl gelöschter Dateien
     */
    public function clearCache(): int
    {
        $count = 0;
        if ($this->cacheDir !== null && is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                        ++$count;
                    }
                }
            }
        }
        return $count;
    }

    // =========================================================================
    // Private Methoden
    // =========================================================================

    /**
     * Rendert eine PDF-Seite als Bild mit dem besten verfügbaren Tool
     *
     * @param string $pdfPath Pfad zur PDF-Datei
     * @return string|null Pfad zum generierten Bild
     */
    private function renderPdfToImage(string $pdfPath): ?string
    {
        $tool = self::getDetectedTool();

        return match ($tool) {
            'pdftoppm' => $this->renderWithPdftoppm($pdfPath),
            'pdftocairo' => $this->renderWithPdftocairo($pdfPath),
            'gs' => $this->renderWithGhostscript($pdfPath),
            'imagick' => $this->renderWithImagick($pdfPath),
            default => null,
        };
    }

    /**
     * Rendert PDF mit pdftoppm (poppler-utils)
     * Empfohlene Methode - nicht von ImageMagick-Policy betroffen
     *
     * @param string $pdfPath Pfad zur PDF-Datei
     * @return string|null Pfad zum generierten Bild
     */
    private function renderWithPdftoppm(string $pdfPath): ?string
    {
        $outputBase = $this->getTempPath('pdftoppm_');

        $formatArg = ($this->format === 'png') ? '-png' : '-jpeg';
        $qualityArg = ($this->format !== 'png') ? ' -jpegopt quality=' . $this->quality : '';

        // Skalierungsoption
        $scaleArg = '';
        if ($this->maxWidth > 0 && $this->maxHeight > 0) {
            $scaleArg = ' -scale-to-x ' . $this->maxWidth . ' -scale-to-y ' . $this->maxHeight;
        } elseif ($this->maxWidth > 0) {
            $scaleArg = ' -scale-to-x ' . $this->maxWidth . ' -scale-to-y -1';
        } elseif ($this->maxHeight > 0) {
            $scaleArg = ' -scale-to-x -1 -scale-to-y ' . $this->maxHeight;
        }

        $cmd = 'pdftoppm'
            . ' -r ' . $this->dpi
            . ' -f ' . $this->page
            . ' -l ' . $this->page
            . ' -singlefile'
            . $scaleArg
            . ' ' . $formatArg
            . $qualityArg
            . ' ' . escapeshellarg($pdfPath)
            . ' ' . escapeshellarg($outputBase);

        exec($cmd, $output, $returnCode);

        $ext = ($this->format === 'png') ? '.png' : '.jpg';
        $outputFile = $outputBase . $ext;

        if ($returnCode !== 0 || !file_exists($outputFile)) {
            rex_logger::factory()->warning('PdfThumbnail: pdftoppm fehlgeschlagen (Code {code}): {output}', ['code' => $returnCode, 'output' => implode(' ', $output)]);
            @unlink($outputFile);
            return null;
        }

        return $outputFile;
    }

    /**
     * Rendert PDF mit pdftocairo (poppler-utils)
     * Alternative wenn pdftoppm nicht verfügbar
     *
     * @param string $pdfPath Pfad zur PDF-Datei
     * @return string|null Pfad zum generierten Bild
     */
    private function renderWithPdftocairo(string $pdfPath): ?string
    {
        $ext = ($this->format === 'png') ? '.png' : '.jpg';
        $outputFile = $this->getTempPath('pdftocairo_') . $ext;

        $formatArg = ($this->format === 'png') ? '-png' : '-jpeg';

        // Skalierungsoption
        $scaleArg = '';
        if ($this->maxWidth > 0) {
            $scaleArg = ' -scale-to-x ' . $this->maxWidth . ' -scale-to-y -1';
        } elseif ($this->maxHeight > 0) {
            $scaleArg = ' -scale-to-x -1 -scale-to-y ' . $this->maxHeight;
        }

        $cmd = 'pdftocairo'
            . ' -r ' . $this->dpi
            . ' -f ' . $this->page
            . ' -l ' . $this->page
            . ' -singlefile'
            . $scaleArg
            . ' ' . $formatArg
            . ' ' . escapeshellarg($pdfPath)
            . ' ' . escapeshellarg(substr($outputFile, 0, -strlen($ext)));

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputFile)) {
            rex_logger::factory()->warning('PdfThumbnail: pdftocairo fehlgeschlagen (Code {code}): {output}', ['code' => $returnCode, 'output' => implode(' ', $output)]);
            @unlink($outputFile);
            return null;
        }

        return $outputFile;
    }

    /**
     * Rendert PDF mit Ghostscript direkt (ohne ImageMagick-Umweg)
     * Umgeht die ImageMagick-Policy komplett
     *
     * @param string $pdfPath Pfad zur PDF-Datei
     * @return string|null Pfad zum generierten Bild
     */
    private function renderWithGhostscript(string $pdfPath): ?string
    {
        $ext = ($this->format === 'png') ? '.png' : '.jpg';
        $outputFile = $this->getTempPath('gs_') . $ext;

        $device = ($this->format === 'png') ? 'png16m' : 'jpeg';
        $qualityArg = ($this->format !== 'png') ? ' -dJPEGQ=' . $this->quality : '';

        $cmd = 'gs'
            . ' -dNOPAUSE'
            . ' -dBATCH'
            . ' -dSAFER'
            . ' -dQUIET'
            . ' -sDEVICE=' . $device
            . ' -r' . $this->dpi
            . ' -dFirstPage=' . $this->page
            . ' -dLastPage=' . $this->page
            . $qualityArg
            . ' -sOutputFile=' . escapeshellarg($outputFile)
            . ' ' . escapeshellarg($pdfPath);

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputFile)) {
            rex_logger::factory()->warning('PdfThumbnail: Ghostscript fehlgeschlagen (Code {code}): {output}', ['code' => $returnCode, 'output' => implode(' ', $output)]);
            @unlink($outputFile);
            return null;
        }

        // Ggf. nachträglich resizen
        // Ghostscript kennt keine direkte Scale-Option, daher über GD
        if ($this->maxWidth > 0 || $this->maxHeight > 0) {
            return $this->resizeImage($outputFile);
        }

        return $outputFile;
    }

    /**
     * Rendert PDF mit PHP Imagick-Extension
     * Fallback - funktioniert evtl. trotz ImageMagick-Policy
     *
     * @param string $pdfPath Pfad zur PDF-Datei
     * @return string|null Pfad zum generierten Bild
     */
    private function renderWithImagick(string $pdfPath): ?string
    {
        if (!class_exists(\Imagick::class)) {
            return null;
        }

        $ext = ($this->format === 'png') ? '.png' : '.jpg';
        $outputFile = $this->getTempPath('imagick_') . $ext;

        try {
            $imagick = new \Imagick();
            $imagick->setResolution($this->dpi, $this->dpi);
            // Seite ist 0-basiert bei Imagick
            $imagick->readImage($pdfPath . '[' . ($this->page - 1) . ']');

            $imagick->setImageBackgroundColor('#' . $this->backgroundColor);
            $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $imagick->transformImageColorspace(\Imagick::COLORSPACE_RGB);

            if ($this->format === 'jpg') {
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality($this->quality);
            } else {
                $imagick->setImageFormat('png');
            }

            // Resize
            if ($this->maxWidth > 0 || $this->maxHeight > 0) {
                $imagick->thumbnailImage(
                    $this->maxWidth > 0 ? $this->maxWidth : 0,
                    $this->maxHeight > 0 ? $this->maxHeight : 0,
                    true
                );
            }

            $imagick->writeImage($outputFile);
            $imagick->destroy();

            return $outputFile;
        } catch (\ImagickException $e) {
            rex_logger::factory()->warning('PdfThumbnail: Imagick fehlgeschlagen: {message}', ['message' => $e->getMessage()]);
            @unlink($outputFile);
            return null;
        }
    }

    /**
     * Verkleinert ein Bild mit GD auf die maximale Größe
     *
     * @param string $imagePath Pfad zum Bild
     * @return string Pfad zum (ggf. verkleinerten) Bild
     */
    private function resizeImage(string $imagePath): string
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return $imagePath; // GD nicht verfügbar, Original zurückgeben
        }

        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return $imagePath;
        }

        $origWidth = $imageInfo[0];
        $origHeight = $imageInfo[1];

        // Berechne neue Dimensionen
        $newWidth = $origWidth;
        $newHeight = $origHeight;

        if ($this->maxWidth > 0 && $origWidth > $this->maxWidth) {
            $ratio = $this->maxWidth / $origWidth;
            $newWidth = $this->maxWidth;
            $newHeight = (int) round($origHeight * $ratio);
        }
        if ($this->maxHeight > 0 && $newHeight > $this->maxHeight) {
            $ratio = $this->maxHeight / $newHeight;
            $newHeight = $this->maxHeight;
            $newWidth = (int) round($newWidth * $ratio);
        }

        // Kein Resize nötig
        if ($newWidth === $origWidth && $newHeight === $origHeight) {
            return $imagePath;
        }

        // Quellbild laden
        $srcImage = match ($imageInfo['mime']) {
            'image/png' => @imagecreatefrompng($imagePath),
            'image/jpeg' => @imagecreatefromjpeg($imagePath),
            default => null,
        };

        if ($srcImage === null || $srcImage === false) {
            return $imagePath;
        }

        /** @var int<1, max> $newWidth */
        /** @var int<1, max> $newHeight */
        $destImage = imagecreatetruecolor($newWidth, $newHeight);
        if ($destImage === false) {
            return $imagePath;
        }

        // Hintergrundfarbe setzen
        /** @var int<0, 255> $red */
        $red = (int) hexdec(substr($this->backgroundColor, 0, 2));
        /** @var int<0, 255> $green */
        $green = (int) hexdec(substr($this->backgroundColor, 2, 2));
        /** @var int<0, 255> $blue */
        $blue = (int) hexdec(substr($this->backgroundColor, 4, 2));
        $bgColor = imagecolorallocate($destImage, $red, $green, $blue);
        if ($bgColor !== false) {
            imagefill($destImage, 0, 0, $bgColor);
        }

        imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        imagedestroy($srcImage);

        // Überschreibe die Datei
        if ($this->format === 'png') {
            imagepng($destImage, $imagePath, 6);
        } else {
            imagejpeg($destImage, $imagePath, $this->quality);
        }

        imagedestroy($destImage);

        return $imagePath;
    }

    /**
     * Wendet Gamma-Korrektur auf ein Bild an
     *
     * @param string $imagePath Pfad zum Bild
     * @return string Pfad zum korrigierten Bild
     */
    private function applyGammaCorrection(string $imagePath): string
    {
        // 1. Bevorzugt: PHP Imagick Extension
        if (class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick($imagePath);
                $im->gammaImage($this->gamma);
                $im->writeImage($imagePath);
                $im->destroy();
                return $imagePath;
            } catch (\ImagickException $e) {
                rex_logger::factory()->warning('PdfThumbnail: Imagick Gamma fehlgeschlagen: {message}', ['message' => $e->getMessage()]);
            }
        }

        // 2. Fallback: convert (ImageMagick CLI)
        if (self::isToolAvailable('convert')) {
            $cmd = 'convert'
                . ' ' . escapeshellarg($imagePath)
                . ' -gamma ' . escapeshellarg((string) $this->gamma)
                . ' ' . escapeshellarg($imagePath);
            exec($cmd, $output, $returnCode);
            if ($returnCode === 0) {
                return $imagePath;
            }
            rex_logger::factory()->warning('PdfThumbnail: convert Gamma fehlgeschlagen (Code {code})', ['code' => $returnCode]);
        }

        // 3. Fallback: GD gammaCorrect
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return $imagePath;
        }

        $srcImage = match ($imageInfo['mime']) {
            'image/png' => @imagecreatefrompng($imagePath),
            'image/jpeg' => @imagecreatefromjpeg($imagePath),
            default => null,
        };

        if ($srcImage === null || $srcImage === false) {
            return $imagePath;
        }

        // GD Gamma: input=1.0, output=1/gamma (invertiert, weil GD anders arbeitet)
        imagegammacorrect($srcImage, 1.0, $this->gamma);

        if ($this->format === 'png') {
            imagepng($srcImage, $imagePath, 6);
        } else {
            imagejpeg($srcImage, $imagePath, $this->quality);
        }

        imagedestroy($srcImage);
        return $imagePath;
    }

    /**
     * Bettet ein sRGB ICC-Profil in das Bild ein
     *
     * Sucht nach verfügbaren ICC-Profilen auf dem System und bettet
     * das sRGB-Profil via Imagick in das Bild ein.
     *
     * @param string $imagePath Pfad zum Bild
     * @return string Pfad zum Bild (mit oder ohne Profil)
     */
    private function embedSrgbIccProfile(string $imagePath): string
    {
        // 1. Bevorzugt: PHP Imagick Extension
        if (class_exists(\Imagick::class)) {
            $profileData = self::findSrgbIccProfile();
            if ($profileData === null) {
                return $imagePath;
            }

            try {
                $im = new \Imagick($imagePath);
                $im->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
                $im->profileImage('icc', $profileData);
                $im->writeImage($imagePath);
                $im->destroy();
                return $imagePath;
            } catch (\ImagickException $e) {
                rex_logger::factory()->warning('PdfThumbnail: Imagick ICC fehlgeschlagen: {message}', ['message' => $e->getMessage()]);
            }
        }

        // 2. Fallback: convert (ImageMagick CLI) mit ICC-Profil-Datei
        if (self::isToolAvailable('convert')) {
            $profilePath = self::findSrgbIccProfilePath();
            if ($profilePath !== null) {
                $cmd = 'convert'
                    . ' ' . escapeshellarg($imagePath)
                    . ' -profile ' . escapeshellarg($profilePath)
                    . ' ' . escapeshellarg($imagePath);
                exec($cmd, $output, $returnCode);
                if ($returnCode === 0) {
                    return $imagePath;
                }
                rex_logger::factory()->warning('PdfThumbnail: convert ICC fehlgeschlagen (Code {code})', ['code' => $returnCode]);
            }
        }

        return $imagePath;
    }

    /**
     * Sucht ein sRGB ICC-Profil auf dem System und gibt den Dateipfad zurück
     *
     * Wird für den convert-CLI-Fallback benötigt, der einen Dateipfad statt Binärdaten erwartet.
     *
     * @return string|null Pfad zur ICC-Profil-Datei oder null
     */
    private static function findSrgbIccProfilePath(): ?string
    {
        foreach (self::getIccProfilePaths() as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Gibt alle bekannten Pfade zu sRGB ICC-Profilen zurück
     *
     * @return list<string> Liste der Pfade
     */
    private static function getIccProfilePaths(): array
    {
        $paths = [
            // Mitgeliefertes sRGB-Profil via TCPDF (immer verfügbar)
            rex_addon::get('pdfout')->getPath('vendor/tecnickcom/tcpdf/include/sRGB.icc'),
            // Standard Linux-Pfade
            '/usr/share/color/icc/colord/sRGB.icc',
            '/usr/share/color/icc/sRGB.icc',
            // Ghostscript-Symlink (Debian/Ubuntu)
            '/usr/share/color/icc/ghostscript/srgb.icc',
        ];

        // Ghostscript versioniertes sRGB-Profil
        $gsPaths = glob('/usr/share/ghostscript/*/iccprofiles/default_rgb.icc') ?: [];
        $paths = array_merge($paths, $gsPaths);

        // dompdf mitgeliefertes Profil
        $paths[] = rex_addon::get('pdfout')->getPath('vendor/dompdf/dompdf/lib/res/sRGB2014.icc');

        // macOS
        $paths[] = '/System/Library/ColorSync/Profiles/sRGB Profile.icc';

        return $paths;
    }

    /**
     * Sucht ein sRGB ICC-Profil auf dem System
     *
     * @return string|null Binärer ICC-Profil-Inhalt oder null
     */
    private static function findSrgbIccProfile(): ?string
    {
        foreach (self::getIccProfilePaths() as $path) {
            if (file_exists($path)) {
                $data = file_get_contents($path);
                if ($data !== false && $data !== '') {
                    return $data;
                }
            }
        }

        return null;
    }

    /**
     * Prüft ob ein Kommandozeilen-Tool verfügbar ist
     *
     * @param string $tool Name des Tools
     * @return bool
     */
    private static function isToolAvailable(string $tool): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $output = [];
        $returnCode = 0;
        @exec('command -v ' . escapeshellarg($tool) . ' 2>/dev/null', $output, $returnCode);

        return $returnCode === 0 && count($output) > 0;
    }

    /**
     * Erzeugt einen Cache-Key basierend auf Datei und Einstellungen
     *
     * @param string $pdfPath Pfad zur PDF-Datei
     * @return string Cache-Key
     */
    private function getCacheKey(string $pdfPath): string
    {
        return md5(implode('|', [
            $pdfPath,
            (string) filemtime($pdfPath),
            $this->format,
            (string) $this->quality,
            (string) $this->dpi,
            (string) $this->page,
            (string) $this->maxWidth,
            (string) $this->maxHeight,
            $this->backgroundColor,
            (string) $this->gamma,
            $this->embedIccProfile ? 'icc' : 'no-icc',
        ]));
    }

    /**
     * Erzeugt einen temporären Dateipfad
     *
     * @param string $prefix Dateiname-Prefix
     * @return string Pfad ohne Dateiendung
     */
    private function getTempPath(string $prefix = ''): string
    {
        return $this->cacheDir . $prefix . md5(uniqid('pdfthumb_', true));
    }
}
