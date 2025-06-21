<?php
use FriendsOfRedaxo\PdfOut\PdfOut as NewPdfOut;

/**
 * PdfOut class for backwards compatibility
 * 
 * This class extends the new PdfOut class from the FriendsOfRedaxo\PdfOut namespace
 * to maintain backwards compatibility with existing code.
 *
 * @deprecated since version 8.5.0, to be removed in 9.0.0. Use FriendsOfRedaxo\PdfOut\PdfOut instead.
 */
class PdfOut extends NewPdfOut
{
    public function __construct()
    {
        parent::__construct();
    }
}
