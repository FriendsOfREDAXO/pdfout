<?php

namespace ahoi\Pdf;

use Dompdf\Dompdf;
use JetBrains\PhpStorm\NoReturn;

class Generator
{
    private Dompdf $dompdf;

    private string $name;

    private string $path;

    private string $html;

    private string $orientation = 'portrait';

    private string $font = 'Helvetica';

    private bool $attachment = false;

    private bool $remote = true;

    private int $dpi = 100;

    public function __construct(string $name = '')
    {
        if (!$name) {
            $name = basename(get_permalink());
        }

        $this->name = $name;
        $this->dompdf = new Dompdf();
    }

    public function setHtml(string $html): Generator
    {
        $this->html = $html;

        return $this;
    }

    public function setOrientation(string $orientation): Generator
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function setFont(string $font): Generator
    {
        $this->font = $font;

        return $this;
    }

    public function setAttachment(bool $attachment): Generator
    {
        $this->attachment = $attachment;

        return $this;
    }

    public function setRemote(bool $remote): Generator
    {
        $this->remote = $remote;

        return $this;
    }

    public function setPath(string $path): Generator
    {
        $this->path = $path;

        return $this;
    }

    public function setDpi(int $dpi): Generator
    {
        $this->dpi = $dpi;

        return $this;
    }

    public function setSaveAndSend(bool $save_and_send): Generator
    {
        $this->save_and_send = $save_and_send;

        return $this;
    }

    public function getDompdf(): Dompdf
    {
        return $this->dompdf;
    }

    public function save(): void
    {
        /** @var \WP_Filesystem_Base $wp_filesystem */
        global $wp_filesystem;

        if (!isset($this->path)) {
            throw new \InvalidArgumentException('Path not set');
        }

        $this->render();

        $data = $this->dompdf->output();
        if ($data === null) {
            return;
        }

        include_once(ABSPATH.'wp-admin/includes/file.php');

        $url = wp_nonce_url('plugins.php');
        $credentials = request_filesystem_credentials($url, '', false, false);

        if (!WP_Filesystem($credentials)) {
            return;
        }

        if (!$wp_filesystem->is_dir($this->path)) {
            $wp_filesystem->mkdir($this->path);
        }
        $wp_filesystem->put_contents($this->path.normalize_whitespace($this->name).'.pdf', $data);
    }

    #[NoReturn]
    public function send(): void
    {
        $this->render();

        while (ob_get_level()) {
            ob_end_clean();
        }

        $this->dompdf->stream(normalize_whitespace($this->name), [
            'Attachment' => $this->attachment,
        ]);

        die();
    }

    private function render(): void
    {
        $options = $this->dompdf->getOptions()
            #->setDebugCss(true)
            ->setChroot(ABSPATH)
            ->setDefaultFont($this->font)
            ->setDpi($this->dpi)
            ->setFontCache(WP_CONTENT_DIR.'/cache/ahoi-pdf/fonts')
            ->setIsRemoteEnabled($this->remote);

        #$this->dompdf->setBasePath(ABSPATH);
        $this->dompdf->setOptions($options);
        $this->dompdf->loadHtml($this->html);
        $this->dompdf->setPaper('A4', $this->orientation);
        $this->dompdf->render();
    }
}
