<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\PdfOut;

/**
 * Sichtbares Signaturfeld: Position in Millimetern ab der linken oberen Ecke der Seite.
 */
final class SignatureField
{
    /**
     * @param int $page Seite (1 = erste, -1 = letzte)
     * @param bool $drawBox Kasten mit Name, Datum, Ort und Grund zeichnen (sonst nur das Feld)
     */
    public function __construct(
        public readonly float $x = 15,
        public readonly float $y = 250,
        public readonly float $width = 70,
        public readonly float $height = 25,
        public readonly int $page = -1,
        public readonly bool $drawBox = true,
        public readonly string $name = 'Signatur',
    ) {
    }

    /** unten links auf der letzten Seite (A4) */
    public static function bottomLeft(float $width = 70, float $height = 25): self
    {
        return new self(15, 297 - 15 - $height, $width, $height);
    }

    /** unten rechts auf der letzten Seite (A4) */
    public static function bottomRight(float $width = 70, float $height = 25): self
    {
        return new self(210 - 15 - $width, 297 - 15 - $height, $width, $height);
    }

    /** frei platziert */
    public static function at(float $x, float $y, float $width = 70, float $height = 25, int $page = -1): self
    {
        return new self($x, $y, $width, $height, $page);
    }

    /** nur das Feld, ohne gezeichneten Kasten (z. B. wenn das Layout selbst eine Fläche vorsieht) */
    public function withoutBox(): self
    {
        return new self($this->x, $this->y, $this->width, $this->height, $this->page, false, $this->name);
    }
}
