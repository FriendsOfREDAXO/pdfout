<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\PdfOut;

use RuntimeException;

/**
 * Eine Funktion benötigt die poppler-utils, die auf diesem Server fehlen (Prüfen, Text, Metadaten).
 * Vorab prüfen mit Poppler::isAvailable() bzw. PdfDocument::canInspect().
 */
final class PopplerUnavailableException extends RuntimeException
{
    public static function forFeature(string $feature): self
    {
        $missing = Poppler::missing();
        return new self(sprintf(
            '%s benötigt die poppler-utils (nicht gefunden: %s). Installation z. B. „apt install poppler-utils“ oder „brew install poppler“; der Ordner lässt sich in den pdfout-Einstellungen festlegen.',
            $feature,
            implode(', ', [] !== $missing ? $missing : Poppler::REQUIRED),
        ));
    }
}
