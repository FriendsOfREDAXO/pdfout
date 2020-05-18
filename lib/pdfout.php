<?php
class pdfout extends \Dompdf\Dompdf
{
    public function __construct($options = null)
    {
        $options = [
                    "font_cache" => rex_path::addonCache('pdfout', 'fonts'),
    ];

        parent::__construct($options);
    }
}

