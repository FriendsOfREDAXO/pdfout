<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\PdfOut\Backend;

use FriendsOfRedaxo\PdfOut\Certificate;
use FriendsOfRedaxo\PdfOut\PdfDocument;
use FriendsOfRedaxo\PdfOut\PdfOut;
use FriendsOfRedaxo\PdfOut\Permission;
use FriendsOfRedaxo\PdfOut\Poppler;
use FriendsOfRedaxo\PdfOut\SignatureField;
use InvalidArgumentException;
use ReflectionMethod;
use rex;
use rex_article;
use rex_file;
use rex_media;
use rex_path;
use rex_sql;
use rex_url;
use Throwable;

/**
 * Demos der Backend-Seite „Demos“.
 *
 * Jede Demo ist eine eigene Methode: Die Seite zeigt den Quelltext der Methode (per Reflection)
 * und führt genau diese Methode aus – angezeigter und ausgeführter Code sind also identisch.
 */
final class Demo
{
    /** Passwort der Demo „Passwortschutz“ */
    public const PASSWORD = 'demo';

    /**
     * @return array<string, array{title: string, description: string, method: string, filename: string, result: 'pdf'|'signatures'|'viewer', certificate: bool, poppler: bool}>
     */
    public static function all(): array
    {
        return [
            'html' => [
                'title' => 'Einfaches PDF aus HTML',
                'description' => 'HTML und CSS werden mit dompdf in ein PDF umgewandelt – Papierformat und Schrift kommen aus den Einstellungen.',
                'method' => 'html', 'filename' => 'hallo-redaxo.pdf', 'result' => 'pdf', 'certificate' => false, 'poppler' => false,
            ],
            'article' => [
                'title' => 'REDAXO-Artikel als PDF',
                'description' => 'Der Startartikel der Website wird samt Inhalt (und OUTPUT_FILTER) als PDF ausgegeben.',
                'method' => 'article', 'filename' => 'startartikel.pdf', 'result' => 'pdf', 'certificate' => false, 'poppler' => false,
            ],
            'stamp' => [
                'title' => 'Seitenzahlen und Stempel „ENTWURF“',
                'description' => 'Mit ->with() lässt sich das fertige PDF weiterbearbeiten, hier mit Seitenzahlen und einem halbtransparenten Stempel.',
                'method' => 'stamp', 'filename' => 'entwurf.pdf', 'result' => 'pdf', 'certificate' => false, 'poppler' => false,
            ],
            'protect' => [
                'title' => 'Passwortschutz',
                'description' => 'Das PDF wird mit AES-256 verschlüsselt und lässt sich nur mit dem Passwort „' . self::PASSWORD . '“ öffnen; erlaubt ist nur das Drucken.',
                'method' => 'protect', 'filename' => 'geschuetzt.pdf', 'result' => 'pdf', 'certificate' => false, 'poppler' => false,
            ],
            'sign' => [
                'title' => 'Digitale Signatur (sichtbar)',
                'description' => 'Das PDF wird mit dem Standard-Zertifikat signiert (PAdES, SHA-256); unten rechts auf der letzten Seite erscheint ein Signaturfeld.',
                'method' => 'sign', 'filename' => 'signiert.pdf', 'result' => 'pdf', 'certificate' => true, 'poppler' => false,
            ],
            'invoice' => [
                'title' => 'Rechnung mit angehängten AGB',
                'description' => 'An eine Rechnung wird ein zweites PDF angehängt – hier werden die AGB selbst erzeugt, im Alltag kommen sie z. B. aus dem Medienpool.',
                'method' => 'invoice', 'filename' => 'rechnung.pdf', 'result' => 'pdf', 'certificate' => false, 'poppler' => false,
            ],
            'edit' => [
                'title' => 'PDF bearbeiten: zusammenführen und Seiten auswählen',
                'description' => 'Zwei PDFs werden zusammengeführt, danach bleiben nur ausgewählte Seiten in neuer Reihenfolge übrig. Beim Bearbeiten gehen Links, Formularfelder und Lesezeichen verloren.',
                'method' => 'edit', 'filename' => 'auswahl.pdf', 'result' => 'pdf', 'certificate' => false, 'poppler' => false,
            ],
            'verify' => [
                'title' => 'Signatur prüfen',
                'description' => 'Ein signiertes PDF wird in eine temporäre Datei geschrieben und mit Poppler (pdfsig) geprüft; das Ergebnis erscheint als Tabelle.',
                'method' => 'verify', 'filename' => '', 'result' => 'signatures', 'certificate' => true, 'poppler' => true,
            ],
            'viewer' => [
                'title' => 'PDF im Viewer anzeigen',
                'description' => 'PdfOut::viewer() liefert die Adresse des mitgelieferten pdf.js-Viewers – mit der Toolbar-Konfiguration des Addons. Gezeigt wird das erste PDF aus dem Medienpool.',
                'method' => 'viewer', 'filename' => '', 'result' => 'viewer', 'certificate' => false, 'poppler' => false,
            ],
        ];
    }

    /** PDF einer Demo erzeugen (ohne es auszugeben) */
    public static function build(string $key): PdfOut|PdfDocument
    {
        return match ($key) {
            'html' => self::html(),
            'article' => self::article(),
            'stamp' => self::stamp(),
            'protect' => self::protect(),
            'sign' => self::sign(),
            'invoice' => self::invoice(),
            'edit' => self::edit(),
            default => throw new InvalidArgumentException('Unbekannte Demo: ' . $key),
        };
    }

    // ------------------------------------------------------------------ Demos

    public static function html(): PdfOut
    {
        return PdfOut::create()
            ->paper('A4', 'portrait')
            ->html('
                <h1 style="color:#1d4f7c">Hallo REDAXO</h1>
                <p>Dieses PDF wurde am ' . date('d.m.Y \u\m H:i') . ' Uhr aus HTML erzeugt.</p>
                <table style="width:100%; border-collapse:collapse">
                    <tr><th style="text-align:left; border-bottom:1px solid #999">Baustein</th><th style="text-align:left; border-bottom:1px solid #999">Aufgabe</th></tr>
                    <tr><td>PdfOut</td><td>HTML → PDF</td></tr>
                    <tr><td>PdfDocument</td><td>PDFs bearbeiten</td></tr>
                </table>
            ');
    }

    public static function article(): PdfOut
    {
        return PdfOut::create()
            ->article(rex_article::getSiteStartArticleId());
    }

    public static function stamp(): PdfOut
    {
        $html = '';
        foreach (['Einleitung', 'Planung', 'Umsetzung'] as $chapter) {
            $html .= '<h1>' . $chapter . '</h1><p>Text zum Kapitel „' . $chapter . '“.</p>'
                . '<div style="page-break-after: always"></div>';
        }

        return PdfOut::create()
            ->html($html)
            ->with(fn (PdfDocument $d): PdfDocument => $d
                ->pageNumbers('Seite {page} von {pages}', position: 'bottom-right')
                ->stamp('ENTWURF', size: 80, opacity: 0.12));
    }

    public static function protect(): PdfOut
    {
        return PdfOut::create()
            ->html('<h1>Vertraulich</h1><p>Dieses PDF öffnet sich nur mit dem Passwort.</p>')
            ->protect(self::PASSWORD, allow: [Permission::Print, Permission::PrintHigh]);
    }

    public static function sign(): PdfOut
    {
        return PdfOut::create()
            ->html('<h1>Freigabe</h1><p>Dieses Dokument ist digital signiert.</p>')
            ->sign(
                Certificate::fromAddon(),
                reason: 'Freigabe',
                location: 'REDAXO',
                field: SignatureField::bottomRight(),
            );
    }

    public static function invoice(): PdfOut
    {
        $agb = PdfDocument::fromHtml('
            <h1>Allgemeine Geschäftsbedingungen</h1>
            <h2>§ 1 Geltungsbereich</h2><p>Diese AGB gelten für alle Bestellungen.</p>
            <h2>§ 2 Zahlung</h2><p>Rechnungen sind innerhalb von 14 Tagen zahlbar.</p>
        ', 'agb.pdf');

        return PdfOut::create()
            ->html('
                <h1>Rechnung 2026-0042</h1>
                <table style="width:100%">
                    <tr><td>Webdesign</td><td style="text-align:right">1.200,00 €</td></tr>
                    <tr><td>Hosting (12 Monate)</td><td style="text-align:right">240,00 €</td></tr>
                    <tr><th style="text-align:left">Summe</th><th style="text-align:right">1.440,00 €</th></tr>
                </table>
            ')
            ->append($agb)
            ->with(fn (PdfDocument $d): PdfDocument => $d->pageNumbers(skipFirst: true));
    }

    public static function edit(): PdfDocument
    {
        $html = '';
        foreach (range(1, 4) as $n) {
            $html .= '<h1>Bericht – Seite ' . $n . '</h1>' . ($n < 4 ? '<div style="page-break-after: always"></div>' : '');
        }
        $bericht = PdfDocument::fromHtml($html, 'bericht.pdf');
        $anhang = PdfDocument::fromHtml('<h1>Anhang</h1><p>Wird hinten angefügt.</p>', 'anhang.pdf');

        // Seite 1, Seite 3 und die letzte Seite (= Anhang) behalten
        return $bericht
            ->append($anhang)
            ->pages('1,3,-1')
            ->metadata(title: 'Auswahl aus dem Bericht', author: 'REDAXO pdfout')
            ->filename('auswahl.pdf');
    }

    /**
     * @return list<array{field: string, signer: string, subject: string, signed_at: string, hash: string, type: string, valid: bool, status: string, certificate: string, certificate_trusted: bool, whole_document: bool}>
     */
    public static function verify(): array
    {
        $file = rex_path::addonCache('pdfout', 'demo-signiert-' . bin2hex(random_bytes(4)) . '.pdf');
        PdfOut::create()
            ->html('<h1>Signiertes Dokument</h1><p>Erstellt am ' . date('d.m.Y H:i') . '</p>')
            ->sign(Certificate::fromAddon(), reason: 'Demo: Signatur prüfen')
            ->save($file);

        try {
            return Poppler::signatures($file);
        } finally {
            rex_file::delete($file);
        }
    }

    public static function viewer(string $file): string
    {
        // pdf.js löst relative Pfade ab dem Viewer-Ordner auf – daher ein Pfad ab der Domain
        $url = rtrim(dirname(rex_server('SCRIPT_NAME', 'string', '/redaxo/index.php')), '/') . '/' . rex_url::media($file);

        return PdfOut::viewer($url);
    }

    // ------------------------------------------------------------------ Hilfen für die Seite

    /** Quelltext einer Demo-Methode (Rumpf ohne Klammern, ausgerückt) */
    public static function source(string $method): string
    {
        $reflection = new ReflectionMethod(self::class, $method);
        $file = (string) $reflection->getFileName();
        $lines = file($file) ?: [];
        $body = array_slice($lines, (int) $reflection->getStartLine(), (int) $reflection->getEndLine() - (int) $reflection->getStartLine());
        // erste Zeile „{“ und letzte Zeile „}“ entfernen
        if ([] !== $body && '{' === trim($body[0])) {
            array_shift($body);
        }
        if ([] !== $body && '}' === trim($body[array_key_last($body)])) {
            array_pop($body);
        }
        $body = array_map(static fn (string $line): string => rtrim(preg_replace('/^ {8}/', '', $line) ?? $line), $body);
        return trim(implode("\n", $body), "\n");
    }

    /** Fehlermeldung, wenn das Standard-Zertifikat fehlt oder sich nicht öffnen lässt; null = alles in Ordnung */
    public static function certificateProblem(): ?string
    {
        try {
            Certificate::fromAddon();
            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /** erstes PDF aus dem Medienpool (oder null) */
    public static function firstMediaPdf(): ?string
    {
        $rows = rex_sql::factory()->getArray('SELECT filename FROM ' . rex::getTable('media') . " WHERE filetype = 'application/pdf' ORDER BY title, filename LIMIT 20");
        foreach ($rows as $row) {
            $name = (string) $row['filename'];
            if (null !== rex_media::get($name) && is_file(rex_path::media($name))) {
                return $name;
            }
        }
        return null;
    }
}
