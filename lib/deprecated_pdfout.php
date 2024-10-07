<?php
use FriendsOfRedaxo\PDFOut\PdfOut as NewPdfOut;

/**
 * PdfOut class for backwards compatibility
 * 
 * This class extends the new PdfOut class from the FriendsOfRedaxo\PDFOut namespace
 * to maintain backwards compatibility with existing code.
 */
class PdfOut extends NewPdfOut
{
    public function __construct()
    {
        parent::__construct();
    }
}
