<?php
/**
 * PDFOut Konfigurationsseite
 */

$addon = rex_addon::get('pdfout');

// Formulardaten verarbeiten
if (rex_post('config-submit', 'bool')) {
    // Input validation
    $errors = [];
    
    // Validiere Signatur-Positionen und -Größen
    $sigX = rex_post('default_signature_position_x', 'int', 180);
    $sigY = rex_post('default_signature_position_y', 'int', 60);
    $sigWidth = rex_post('default_signature_width', 'int', 15);
    $sigHeight = rex_post('default_signature_height', 'int', 15);
    $dpi = rex_post('default_dpi', 'int', 100);
    
    if ($sigX < 0 || $sigX > 300) $errors[] = 'Signatur X-Position muss zwischen 0 und 300 mm liegen';
    if ($sigY < 0 || $sigY > 400) $errors[] = 'Signatur Y-Position muss zwischen 0 und 400 mm liegen';
    if ($sigWidth < 5 || $sigWidth > 100) $errors[] = 'Signatur Breite muss zwischen 5 und 100 mm liegen';
    if ($sigHeight < 5 || $sigHeight > 50) $errors[] = 'Signatur Höhe muss zwischen 5 und 50 mm liegen';
    if ($dpi < 50 || $dpi > 300) $errors[] = 'DPI muss zwischen 50 und 300 liegen';
    
    // Validiere Zertifikatspfad falls angegeben
    $certPath = rex_post('default_certificate_path', 'string', '');
    if (!empty($certPath)) {
        // Pfad-Traversal-Schutz
        $certPath = str_replace(['../', '..\\'], '', $certPath);
        if (!preg_match('/^[a-zA-Z0-9\/\\\\_\-\.]+$/', $certPath)) {
            $errors[] = 'Zertifikatspfad enthält ungültige Zeichen';
        }
    }
    
    // Validiere Performance-Limits
    $maxHtmlSizeMb = rex_post('max_html_size_mb', 'int', 10);
    $maxExecutionTime = rex_post('max_execution_time', 'int', 300);
    $maxCertSizeKb = rex_post('max_certificate_size_kb', 'int', 1024);
    
    if ($maxHtmlSizeMb < 1 || $maxHtmlSizeMb > 100) $errors[] = 'Maximale HTML-Größe muss zwischen 1 und 100 MB liegen';
    if ($maxExecutionTime < 30 || $maxExecutionTime > 1800) $errors[] = 'Maximale Ausführungszeit muss zwischen 30 und 1800 Sekunden liegen';
    if ($maxCertSizeKb < 10 || $maxCertSizeKb > 10240) $errors[] = 'Maximale Zertifikatsgröße muss zwischen 10 KB und 10 MB liegen';
    
    if (empty($errors)) {
        $addon->setConfig([
            // PDF Grundeinstellungen
            'default_paper_size' => rex_post('default_paper_size', 'string', 'A4'),
            'default_orientation' => rex_post('default_orientation', 'string', 'portrait'),
            'default_font' => rex_post('default_font', 'string', 'Dejavu Sans'),
            'default_dpi' => $dpi,
            'default_attachment' => rex_post('default_attachment', 'bool', false),
            'default_remote_files' => rex_post('default_remote_files', 'bool', true),
            
            // Digitale Signatur Einstellungen
            'default_certificate_path' => $certPath,
            'default_certificate_password' => rex_post('default_certificate_password', 'string', ''),
            'enable_signature_by_default' => rex_post('enable_signature_by_default', 'bool', false),
            'enable_password_protection_by_default' => rex_post('enable_password_protection_by_default', 'bool', false),
            'default_signature_position_x' => $sigX,
            'default_signature_position_y' => $sigY,
            'default_signature_width' => $sigWidth,
            'default_signature_height' => $sigHeight,
            
            // System Einstellungen
            'enable_debug_mode' => rex_post('enable_debug_mode', 'bool', false),
            'log_pdf_generation' => rex_post('log_pdf_generation', 'bool', false),
            'temp_file_cleanup' => rex_post('temp_file_cleanup', 'bool', true),
            
            // Performance/Sicherheits-Limits
            'max_html_size_mb' => $maxHtmlSizeMb,
            'max_execution_time' => $maxExecutionTime,
            'max_certificate_size_kb' => $maxCertSizeKb,
        ]);
        
        echo rex_view::success('Konfiguration wurde gespeichert!');
    } else {
        echo rex_view::error('Konfigurationsfehler:<br>' . implode('<br>', $errors));
    }
}

// Aktuelle Konfiguration laden
$config = $addon->getConfig();

// ========================================
// ALLGEMEINE KONFIGURATION
// ========================================

$form = '<form action="' . rex_url::currentBackendPage() . '" method="post">';

// Standard-Einstellungen für PDF-Generierung
$formElements = [];

// PDF Grundeinstellungen
$formElements = [];

$n = [];
$n['label'] = '<label for="default_paper_size"><i class="fa fa-file-o"></i> Standard Papierformat</label>';
$select = new rex_select();
$select->setName('default_paper_size');
$select->setId('default_paper_size');
$select->setAttribute('class', 'form-control');
$select->addOption('A4 (210 × 297 mm)', 'A4');
$select->addOption('A3 (297 × 420 mm)', 'A3');
$select->addOption('A5 (148 × 210 mm)', 'A5');
$select->addOption('Letter (216 × 279 mm)', 'letter');
$select->addOption('Legal (216 × 356 mm)', 'legal');
$select->setSelected($config['default_paper_size'] ?? 'A4');
$n['field'] = $select->get();
$n['note'] = 'Standard-Papierformat für neue PDFs.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_orientation"><i class="fa fa-rotate-right"></i> Standard Ausrichtung</label>';
$select = new rex_select();
$select->setName('default_orientation');
$select->setId('default_orientation');
$select->setAttribute('class', 'form-control');
$select->addOption('Hochformat (Portrait)', 'portrait');
$select->addOption('Querformat (Landscape)', 'landscape');
$select->setSelected($config['default_orientation'] ?? 'portrait');
$n['field'] = $select->get();
$n['note'] = 'Standard-Ausrichtung für neue PDFs.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_font"><i class="fa fa-font"></i> Standard Schriftart</label>';
$select = new rex_select();
$select->setName('default_font');
$select->setId('default_font');
$select->setAttribute('class', 'form-control');
$select->addOption('Dejavu Sans', 'Dejavu Sans');
$select->addOption('Arial', 'Arial');
$select->addOption('Helvetica', 'Helvetica');
$select->addOption('Times', 'Times');
$select->addOption('Courier', 'Courier');
$select->setSelected($config['default_font'] ?? 'Dejavu Sans');
$n['field'] = $select->get();
$n['note'] = 'Standard-Schriftart für neue PDFs.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_dpi"><i class="fa fa-search-plus"></i> Standard DPI-Auflösung</label>';
$select = new rex_select();
$select->setName('default_dpi');
$select->setId('default_dpi');
$select->setAttribute('class', 'form-control');
$select->addOption('72 DPI (Web-Qualität)', '72');
$select->addOption('96 DPI (Standard)', '96');
$select->addOption('100 DPI (REDAXO Standard)', '100');
$select->addOption('150 DPI (Erhöht)', '150');
$select->addOption('300 DPI (Druck-Qualität)', '300');
$select->setSelected($config['default_dpi'] ?? 100);
$n['field'] = $select->get();
$n['note'] = 'DPI-Auflösung für Bilder und Text. Höhere Werte = bessere Qualität, größere Dateien.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_attachment"><i class="fa fa-download"></i> Als Download anbieten</label>';
$n['field'] = '<input type="checkbox" id="default_attachment" name="default_attachment" value="1"' . (($config['default_attachment'] ?? false) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Wenn aktiviert, wird das PDF als Download angeboten anstatt im Browser angezeigt.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_remote_files"><i class="fa fa-cloud"></i> Externe Dateien erlauben</label>';
$n['field'] = '<input type="checkbox" id="default_remote_files" name="default_remote_files" value="1"' . (($config['default_remote_files'] ?? true) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Erlaubt das Laden von externen Bildern und Ressourcen (http/https URLs).';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$basicSettings = $fragment->parse('core/form/form.php');

// Erweiterte Einstellungen
$formElements = [];

$n = [];
$n['label'] = '<label for="enable_signature_by_default"><i class="fa fa-edit"></i> Digitale Signierung standardmäßig aktivieren</label>';
$n['field'] = '<input type="checkbox" id="enable_signature_by_default" name="enable_signature_by_default" value="1"' . (($config['enable_signature_by_default'] ?? false) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Wenn aktiviert, werden alle PDFs standardmäßig digital signiert (sofern Berechtigung und Zertifikat vorhanden).';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="enable_password_protection_by_default"><i class="fa fa-shield"></i> Passwortschutz standardmäßig aktivieren</label>';
$n['field'] = '<input type="checkbox" id="enable_password_protection_by_default" name="enable_password_protection_by_default" value="1"' . (($config['enable_password_protection_by_default'] ?? false) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Wenn aktiviert, sind alle PDFs standardmäßig passwortgeschützt.';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$advancedSettings = $fragment->parse('core/form/form.php');

// Signatur-Position Standard-Einstellungen
$formElements = [];

$n = [];
$n['label'] = '<label for="default_signature_position_x"><i class="fa fa-arrows-h"></i> Standard Signatur Position X (mm)</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_position_x" name="default_signature_position_x" value="' . rex_escape($config['default_signature_position_x'] ?? 180) . '" min="0" max="300"/>';
$n['note'] = 'X-Position der sichtbaren Signatur vom linken Rand (in Millimetern).';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_signature_position_y"><i class="fa fa-arrows-v"></i> Standard Signatur Position Y (mm)</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_position_y" name="default_signature_position_y" value="' . rex_escape($config['default_signature_position_y'] ?? 60) . '" min="0" max="400"/>';
$n['note'] = 'Y-Position der sichtbaren Signatur vom oberen Rand (in Millimetern).';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_signature_width"><i class="fa fa-resize-horizontal"></i> Standard Signatur Breite (mm)</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_width" name="default_signature_width" value="' . rex_escape($config['default_signature_width'] ?? 15) . '" min="5" max="100"/>';
$n['note'] = 'Breite der sichtbaren Signatur (in Millimetern).';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_signature_height"><i class="fa fa-resize-vertical"></i> Standard Signatur Höhe (mm)</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_height" name="default_signature_height" value="' . rex_escape($config['default_signature_height'] ?? 15) . '" min="5" max="50"/>';
$n['note'] = 'Höhe der sichtbaren Signatur (in Millimetern).';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$signatureSettings = $fragment->parse('core/form/form.php');

// System-Einstellungen
$formElements = [];

$n = [];
$n['label'] = '<label for="enable_debug_mode"><i class="fa fa-bug"></i> Debug-Modus aktivieren</label>';
$n['field'] = '<input type="checkbox" id="enable_debug_mode" name="enable_debug_mode" value="1"' . (($config['enable_debug_mode'] ?? false) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Aktiviert erweiterte Fehlerausgaben und Logging für Debugging-Zwecke.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="log_pdf_generation"><i class="fa fa-list-alt"></i> PDF-Generierung protokollieren</label>';
$n['field'] = '<input type="checkbox" id="log_pdf_generation" name="log_pdf_generation" value="1"' . (($config['log_pdf_generation'] ?? false) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Protokolliert alle PDF-Generierungen im REDAXO-Log.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="temp_file_cleanup"><i class="fa fa-trash"></i> Temporäre Dateien automatisch löschen</label>';
$n['field'] = '<input type="checkbox" id="temp_file_cleanup" name="temp_file_cleanup" value="1"' . (($config['temp_file_cleanup'] ?? true) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Löscht temporäre Dateien automatisch nach der PDF-Generierung.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="max_html_size_mb"><i class="fa fa-database"></i> Maximale HTML-Größe (MB)</label>';
$n['field'] = '<input class="form-control" type="number" id="max_html_size_mb" name="max_html_size_mb" value="' . rex_escape($config['max_html_size_mb'] ?? 10) . '" min="1" max="100"/>';
$n['note'] = 'Maximale Größe des HTML-Inhalts in Megabytes zur Vermeidung von Speicherproblemen.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="max_execution_time"><i class="fa fa-clock-o"></i> Maximale Ausführungszeit (Sekunden)</label>';
$n['field'] = '<input class="form-control" type="number" id="max_execution_time" name="max_execution_time" value="' . rex_escape($config['max_execution_time'] ?? 300) . '" min="30" max="1800"/>';
$n['note'] = 'Maximale Zeit für die PDF-Generierung zur Vermeidung von Timeouts.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="max_certificate_size_kb"><i class="fa fa-certificate"></i> Maximale Zertifikatsgröße (KB)</label>';
$n['field'] = '<input class="form-control" type="number" id="max_certificate_size_kb" name="max_certificate_size_kb" value="' . rex_escape($config['max_certificate_size_kb'] ?? 1024) . '" min="10" max="10240"/>';
$n['note'] = 'Maximale Größe von Zertifikatsdateien in Kilobytes.';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$systemSettings = $fragment->parse('core/form/form.php');

// Submit Button
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="config-submit" value="1"><i class="fa fa-save"></i> Konfiguration speichern</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$submitButton = $fragment->parse('core/form/submit.php');

// Tabs für bessere Übersicht
$generalConfigContent = '
<div class="row">
    <div class="col-md-12">
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="active">
                <a href="#basic" aria-controls="basic" role="tab" data-toggle="tab">
                    <i class="fa fa-file-pdf-o"></i> Grundeinstellungen
                </a>
            </li>
            <li role="presentation">
                <a href="#advanced" aria-controls="advanced" role="tab" data-toggle="tab">
                    <i class="fa fa-cog"></i> Erweiterte Features
                </a>
            </li>
            <li role="presentation">
                <a href="#signature" aria-controls="signature" role="tab" data-toggle="tab">
                    <i class="fa fa-edit"></i> Signatur-Position
                </a>
            </li>
            <li role="presentation">
                <a href="#system" aria-controls="system" role="tab" data-toggle="tab">
                    <i class="fa fa-server"></i> System
                </a>
            </li>
        </ul>
        
        <div class="tab-content" style="padding-top: 20px;">
            <div role="tabpanel" class="tab-pane active" id="basic">
                ' . $basicSettings . '
                <div class="alert alert-info">
                    <strong><i class="fa fa-info-circle"></i> Hinweis:</strong>
                    Diese Grundeinstellungen werden für alle neuen PDFs verwendet, wenn nicht explizit 
                    andere Werte in der jeweiligen Implementierung gesetzt werden.
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="advanced">
                ' . $advancedSettings . '
                <div class="alert alert-warning">
                    <strong><i class="fa fa-exclamation-triangle"></i> Erweiterte Features:</strong>
                    Diese Einstellungen aktivieren erweiterte PDF-Features standardmäßig. 
                    Beachten Sie, dass für digitale Signaturen entsprechende Berechtigungen und Zertifikate erforderlich sind.
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="signature">
                ' . $signatureSettings . '
                <div class="alert alert-info">
                    <strong><i class="fa fa-info-circle"></i> Positionierungs-Hilfe:</strong>
                    Die Signatur wird relativ zur linken oberen Ecke des Dokuments positioniert. 
                    Übliche Werte: X=180mm, Y=60mm für eine Signatur rechts unten.
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="system">
                ' . $systemSettings . '
                <div class="alert alert-warning">
                    <strong><i class="fa fa-exclamation-triangle"></i> Debug-Modus:</strong>
                    Aktivieren Sie den Debug-Modus nur in Entwicklungsumgebungen, da sensible Informationen 
                    ausgegeben werden können.
                </div>
                
                ' . (($config['enable_debug_mode'] ?? false) ? '
                <div class="alert alert-danger">
                    <strong><i class="fa fa-exclamation-triangle"></i> Produktionswarnung:</strong>
                    Der Debug-Modus ist aktiviert! Dies kann in Produktionsumgebungen zu Sicherheitsproblemen führen,
                    da interne Pfade und Fehlermeldungen ausgegeben werden.
                </div>' : '') . '
                
                ' . (($config['default_remote_files'] ?? true) ? '
                <div class="alert alert-info">
                    <strong><i class="fa fa-info-circle"></i> Remote-Dateien:</strong>
                    Der Zugriff auf externe Dateien ist aktiviert. Stellen Sie sicher, dass nur vertrauenswürdige 
                    Quellen verwendet werden.
                </div>' : '') . '
            </div>
        </div>
        
        ' . $submitButton . '
    </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Allgemeine Konfiguration');
$fragment->setVar('body', '<form action="' . rex_url::currentBackendPage() . '" method="post">' . $generalConfigContent . '</form>', false);
echo $fragment->parse('core/page/section.php');

// Hinweis auf Demo & Test Bereich
$demoInfo = '
<div class="alert alert-info">
    <h4><i class="fa fa-info-circle"></i> Demo & Test Funktionen</h4>
    <p>Für Demo- und Testzwecke (Test-Zertifikat Generator, Test-PDF etc.) besuchen Sie die 
    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="alert-link">
        <i class="fa fa-play"></i> Demo-Seite
    </a>.</p>
    <p><small>Dort finden Sie am Ende der Seite alle Tools zum Testen und Entwickeln.</small></p>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Demo & Test Funktionen');
$fragment->setVar('body', $demoInfo, false);
echo $fragment->parse('core/page/section.php');
