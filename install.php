<?php

if ($somethingIsWrong) {
    $this->setProperty('installmsg', 'Something is wrong');
    $this->setProperty('install', false);
}

$error = '';
if ($this->getConfig('install') != 'true') {
  $srcdir = '../redaxo/src/addons/pdfout/vendor/';
  rex_dir::copy($srcdir ,'.././assets/addons/pdfout');
}


if(!$error AND !$this->hasConfig()) {
  $this->setConfig('install', true);
}

