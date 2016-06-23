<?php

if (!rex::isBackend()) {
  require_once rex_url::addonAssets('pdfout/dompdf', 'autoload.inc.php');
}

