<?php

/**
 * Erzeugt die Schriften für tc-lib-pdf und legt die benötigten Familien in fonts/ ab.
 *
 * tc-lib-pdf liefert seine Schriften nicht fertig mit, sie werden aus den Quell-Schriften
 * (tc-font-mirror) erzeugt. Das Addon wird mit fertigem vendor/ verteilt – darum werden die
 * Schriften beim Bauen erzeugt und unter fonts/ mitgeliefert.
 *
 * Aufruf (im Addon-Ordner, nach composer install):
 *     php scripts/build-fonts.php [familie ...]
 *
 * Standard: core (Helvetica, Times, Courier … – reicht für Signatur-Felder und Trennseiten).
 * Weitere Familien z. B. „dejavu“ für Unicode-Text: php scripts/build-fonts.php core dejavu
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Nur auf der Kommandozeile ausführen.\n");
    exit(1);
}

$addonDir = dirname(__DIR__);
$fontPackage = $addonDir . '/vendor/tecnickcom/tc-lib-pdf-font';
$families = array_slice($argv, 1) ?: ['core'];

if (!is_dir($fontPackage)) {
    fwrite(STDERR, "tc-lib-pdf-font fehlt – zuerst composer install ausführen.\n");
    exit(1);
}

// Schriften im Paket erzeugen (lädt beim ersten Lauf die Quell-Schriften per Composer)
$command = [PHP_BINARY, 'util/build_fonts.php'];
$process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $fontPackage, array_diff_key(getenv(), ['COMPOSER_BINARY' => true]));
if (false === $process || 0 !== proc_close($process)) {
    fwrite(STDERR, "Schriften konnten nicht erzeugt werden (util/build_fonts.php).\n");
    exit(1);
}

$source = $fontPackage . '/target/fonts';
$target = $addonDir . '/fonts';

$copy = static function (string $from, string $to) use (&$copy): void {
    if (!is_dir($to) && !mkdir($to, 0o775, true) && !is_dir($to)) {
        throw new RuntimeException('Ordner nicht anlegbar: ' . $to);
    }
    foreach (scandir($from) ?: [] as $item) {
        if ('.' === $item || '..' === $item) {
            continue;
        }
        $path = $from . '/' . $item;
        is_dir($path) ? $copy($path, $to . '/' . $item) : copy($path, $to . '/' . $item);
    }
};

foreach ($families as $family) {
    if (!preg_match('/^[a-z0-9_-]+$/', $family) || !is_dir($source . '/' . $family)) {
        fwrite(STDERR, "Unbekannte Schriftfamilie: {$family}\n");
        exit(1);
    }
    $copy($source . '/' . $family, $target . '/' . $family);
    echo "✓ {$family} → fonts/{$family}\n";
}

// erzeugte Dateien im vendor-Paket wieder entfernen (sonst doppelt im Addon-ZIP)
$remove = static function (string $dir) use (&$remove): void {
    foreach (scandir($dir) ?: [] as $item) {
        if ('.' === $item || '..' === $item) {
            continue;
        }
        $path = $dir . '/' . $item;
        is_dir($path) && !is_link($path) ? $remove($path) : unlink($path);
    }
    rmdir($dir);
};
foreach (['target', 'util/vendor'] as $generated) {
    if (is_dir($fontPackage . '/' . $generated)) {
        $remove($fontPackage . '/' . $generated);
    }
}
echo "Fertig.\n";
