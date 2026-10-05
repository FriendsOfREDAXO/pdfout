<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\PdfOut;

/**
 * Rechte eines passwortgeschützten PDFs (ohne Besitzer-Passwort).
 *
 * pdfout arbeitet mit **erlaubten** Rechten: alles, was nicht erlaubt ist, wird gesperrt.
 */
enum Permission: string
{
    /** Drucken (in geringer Qualität, siehe PrintHigh) */
    case Print = 'print';
    /** Drucken in voller Qualität */
    case PrintHigh = 'print-high';
    /** Inhalt ändern */
    case Modify = 'modify';
    /** Text und Bilder kopieren */
    case Copy = 'copy';
    /** Kommentare und Formularfelder bearbeiten */
    case Annotate = 'annot-forms';
    /** Formularfelder ausfüllen */
    case FillForms = 'fill-forms';
    /** Inhalte für Barrierefreiheit auslesen (Screenreader) */
    case Extract = 'extract';
    /** Seiten einfügen, drehen, löschen */
    case Assemble = 'assemble';

    /**
     * Liste in Enum-Werte umwandeln (Strings wie 'print' werden akzeptiert, Unbekanntes ignoriert)
     *
     * @param iterable<Permission|string> $permissions
     * @return list<Permission>
     */
    public static function list(iterable $permissions): array
    {
        $out = [];
        foreach ($permissions as $permission) {
            $case = $permission instanceof self ? $permission : self::tryFrom((string) $permission);
            if (null !== $case && !in_array($case, $out, true)) {
                $out[] = $case;
            }
        }
        return $out;
    }

    /**
     * Zu sperrende Rechte (Format von tc-lib-pdf) aus den erlaubten
     *
     * @param iterable<Permission|string> $allowed
     * @return list<string>
     */
    public static function blockedFor(iterable $allowed): array
    {
        $allowed = self::list($allowed);
        // Barrierefreiheit: Auslesen für Screenreader bleibt immer erlaubt
        $allowed[] = self::Extract;
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => !in_array($case, $allowed, true)),
        ));
    }
}
