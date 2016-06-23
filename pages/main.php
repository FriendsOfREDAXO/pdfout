<?php
$file = rex_file::get(rex_path::addon('pdfout','README.md'));
$Parsedown = new Parsedown();
$content =  '<div id="modulsammlung">'.$Parsedown->text($file);


$fragment = new rex_fragment();
$fragment->setVar('title', $this->i18n('title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');


