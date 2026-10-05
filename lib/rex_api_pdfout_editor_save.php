<?php

declare(strict_types=1);

/**
 * Speichert ein im pdf.js-Editor bearbeitetes PDF in den Medienpool.
 *
 * Aufruf nur aus dem Backend (nicht veröffentlicht, CSRF-geschützt über getUrlParams()).
 * POST-Felder: pdf (Datei), mode (new|replace), original (Dateiname im Medienpool),
 * filename (Name der neuen Datei), category_id (Medienkategorie der neuen Datei).
 * Antwort: JSON {ok, filename, url, editor, message}.
 */
class rex_api_pdfout_editor_save extends rex_api_function
{
    /** Obergrenze für die Größe des bearbeiteten PDFs (zusätzlich gelten upload_max_filesize/post_max_size). */
    public const MAX_SIZE = 50 * 1024 * 1024;

    public const MODE_NEW = 'new';
    public const MODE_REPLACE = 'replace';

    protected $published = false;

    public function execute(): never
    {
        rex_response::cleanOutputBuffers();

        $user = rex::getUser();
        if (null === $user || !self::mayUseEditor($user)) {
            self::reply(rex_response::HTTP_FORBIDDEN, false, 'Keine Berechtigung für den PDF-Editor.');
        }

        if ('post' !== rex_request_method()) {
            self::reply('405 Method Not Allowed', false, 'Nur POST-Anfragen sind erlaubt.');
        }

        $upload = $_FILES['pdf'] ?? null;
        if (!is_array($upload) || !isset($upload['tmp_name'], $upload['error']) || !is_string($upload['tmp_name'])) {
            self::reply(rex_response::HTTP_BAD_REQUEST, false, 'Keine PDF-Daten empfangen (eventuell größer als post_max_size).');
        }

        $error = (int) $upload['error'];
        if (UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error) {
            self::reply(rex_response::HTTP_BAD_REQUEST, false, 'Das PDF ist zu groß (höchstens ' . rex_formatter::bytes(self::maxUploadSize()) . ').');
        }
        if (UPLOAD_ERR_OK !== $error || !is_uploaded_file($upload['tmp_name'])) {
            self::reply(rex_response::HTTP_BAD_REQUEST, false, 'Die Übertragung des PDFs ist fehlgeschlagen.');
        }

        try {
            $result = self::saveFile(
                $upload['tmp_name'],
                rex_post('mode', 'string', self::MODE_NEW),
                rex_post('original', 'string', ''),
                rex_post('filename', 'string', ''),
                rex_post('category_id', 'int', -1),
                $user,
            );
        } catch (rex_api_exception|InvalidArgumentException|RuntimeException $e) {
            self::reply(rex_response::HTTP_BAD_REQUEST, false, $e->getMessage());
        }

        self::reply(rex_response::HTTP_OK, true, $result['message'], $result['filename'], $result['url'], $result['editor']);
    }

    /**
     * Kern der Speicherlogik (ohne HTTP): prüft das PDF, kopiert es in den Addon-Cache und legt es im Medienpool
     * neu an bzw. ersetzt das Original. Die Quelldatei bleibt unverändert, die Cache-Kopie wird immer gelöscht.
     *
     * @param string $sourcePath Pfad zur bearbeiteten PDF-Datei (z. B. Upload-Tempdatei)
     * @param string $mode self::MODE_NEW oder self::MODE_REPLACE
     * @param string $original Dateiname des Originals im Medienpool
     * @param string $targetName gewünschter Dateiname für die neue Datei (nur bei MODE_NEW; leer = <original>-bearbeitet.pdf)
     * @param int $categoryId Medienkategorie der neuen Datei (nur bei MODE_NEW; < 0 = Kategorie des Originals)
     *
     * @throws rex_api_exception bei ungültigen Eingaben oder fehlenden Rechten
     *
     * @return array{ok: true, filename: string, url: string, editor: string, message: string}
     */
    public static function saveFile(string $sourcePath, string $mode, string $original, string $targetName, int $categoryId, rex_user $user): array
    {
        if (!self::mayUseEditor($user)) {
            throw new rex_api_exception('Keine Berechtigung für den PDF-Editor.');
        }
        if (self::MODE_NEW !== $mode && self::MODE_REPLACE !== $mode) {
            throw new rex_api_exception('Unbekannte Speicherart.');
        }

        $originalMedia = '' !== $original ? rex_media::get($original) : null;
        if (null === $originalMedia || !str_ends_with(strtolower($originalMedia->getFileName()), '.pdf')) {
            throw new rex_api_exception('Die Ausgangsdatei wurde im Medienpool nicht gefunden.');
        }
        $original = $originalMedia->getFileName();

        self::assertPdf($sourcePath);

        $mediaPerm = $user->getComplexPerm('media');
        $mayEdit = static fn (int $category): bool => $user->isAdmin()
            || $mediaPerm->hasCategoryPerm($category);

        if (!$mayEdit($originalMedia->getCategoryId())) {
            throw new rex_api_exception('Keine Berechtigung für die Medienkategorie der Ausgangsdatei.');
        }

        $tmp = rex_path::addonCache('pdfout', 'editor/' . bin2hex(random_bytes(8)) . '.pdf');
        if (!rex_file::copy($sourcePath, $tmp)) {
            throw new rex_api_exception('Das PDF konnte nicht zwischengespeichert werden.');
        }

        try {
            if (self::MODE_REPLACE === $mode) {
                $result = rex_media_service::updateMedia($original, [
                    'title' => $originalMedia->getTitle(),
                    'category_id' => $originalMedia->getCategoryId(),
                    // updateMedia() liest den Pfad aus tmp_name
                    'file' => ['name' => $original, 'tmp_name' => $tmp, 'path' => $tmp, 'error' => UPLOAD_ERR_OK],
                ]);
                $filename = (string) $result['filename'];
                $message = 'Original „' . $filename . '“ ersetzt.';
            } else {
                if ($categoryId < 0) {
                    $categoryId = $originalMedia->getCategoryId();
                }
                if (0 !== $categoryId && null === rex_media_category::get($categoryId)) {
                    throw new rex_api_exception('Die Medienkategorie existiert nicht.');
                }
                if (!$mayEdit($categoryId)) {
                    throw new rex_api_exception('Keine Berechtigung für die gewählte Medienkategorie.');
                }

                $title = trim($originalMedia->getTitle());
                $result = rex_media_service::addMedia([
                    'title' => '' !== $title ? $title . ' (bearbeitet)' : '',
                    'category_id' => $categoryId,
                    'file' => ['name' => self::targetFilename($targetName, $original), 'path' => $tmp],
                ], true, ['types' => 'pdf']);
                $filename = (string) $result['filename'];
                $message = 'Neue Datei „' . $filename . '“ im Medienpool gespeichert.';
            }
        } finally {
            rex_file::delete($tmp);
        }

        rex_media_cache::delete($filename);

        return [
            'ok' => true,
            'filename' => $filename,
            'url' => rex_url::media($filename),
            'editor' => rex_url::backendPage('pdfout/editor', ['file' => $filename], false),
            'message' => $message,
        ];
    }

    /**
     * Dateiname für die neue Datei: nur der Basisname, Endung .pdf erzwungen, leer = <original>-bearbeitet.pdf.
     */
    public static function targetFilename(string $requested, string $original): string
    {
        $name = trim(rex_path::basename(str_replace('\\', '/', $requested)));
        if (str_ends_with(strtolower($name), '.pdf')) {
            $name = substr($name, 0, -4);
        }
        if ('' === trim($name, " .\t")) {
            $base = pathinfo($original, PATHINFO_FILENAME);
            $name = $base . '-bearbeitet';
        }
        // keine Mehrfach-Endungen (z. B. „x.php.pdf“ würde der Medienpool zu Recht ablehnen)
        $name = str_replace('.', '-', $name);

        return $name . '.pdf';
    }

    /** Darf der Benutzer den PDF-Editor nutzen (Addon-Recht und Medienpool-Recht)? */
    public static function mayUseEditor(rex_user $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        $mediaPerm = $user->getComplexPerm('media');

        return $user->hasPerm('pdfout[tools]') && $mediaPerm->hasMediaPerm();
    }

    /** Effektive Obergrenze in Byte (MAX_SIZE, upload_max_filesize, post_max_size). */
    public static function maxUploadSize(): int
    {
        $limits = [self::MAX_SIZE];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $value = rex_ini_get($key);
            if ($value > 0) {
                $limits[] = $value;
            }
        }

        return min($limits);
    }

    /**
     * @throws rex_api_exception
     */
    private static function assertPdf(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new rex_api_exception('Die PDF-Daten fehlen.');
        }
        $size = filesize($path);
        if (false === $size || $size < 8) {
            throw new rex_api_exception('Die PDF-Daten sind leer.');
        }
        if ($size > self::MAX_SIZE) {
            throw new rex_api_exception('Das PDF ist zu groß (höchstens ' . rex_formatter::bytes(self::MAX_SIZE) . ').');
        }
        $handle = fopen($path, 'r');
        $head = false !== $handle ? fread($handle, 5) : false;
        if (false !== $handle) {
            fclose($handle);
        }
        if ('%PDF-' !== $head || 'application/pdf' !== rex_file::mimeType($path, 'upload.pdf')) {
            throw new rex_api_exception('Die Daten sind kein gültiges PDF.');
        }
    }

    private static function reply(string $status, bool $ok, string $message, string $filename = '', string $url = '', string $editor = ''): never
    {
        rex_response::setStatus($status);
        rex_response::sendJson([
            'ok' => $ok,
            'filename' => $filename,
            'url' => $url,
            'editor' => $editor,
            'message' => $message,
        ]);
        exit;
    }
}
