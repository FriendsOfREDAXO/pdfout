<?php
use FriendsOfRedaxo\PDFOut\PdfOut as NewPdfOut;

/**
 * PdfOut class for backwards compatibility
 * 
 * This class extends the new PdfOut class from the FriendsOfRedaxo\PDFOut namespace
 * to maintain backwards compatibility with existing code.
 *
 * @deprecated since version 8.5.0, to be removed in 9.0.0. Use FriendsOfRedaxo\PDFOut\PdfOut instead.
 */
class PdfOut extends NewPdfOut
{
    public function __construct()
    {
        parent::__construct();
    }
}
