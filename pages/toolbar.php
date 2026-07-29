<?php

$addon = rex_addon::get('pdfout');

$featureOptions = [
    'sidebar' => 'Seitenleiste / Navigator',
    'search' => 'Suche',
    'navigation' => 'Vor / Zurück',
    'page_number' => 'Seitennummer',
    'zoom' => 'Zoom-Steuerung',
    'open_file' => 'Datei öffnen',
    'print' => 'Drucken',
    'download' => 'Download',
    'editor_tools' => 'Editor-Werkzeuge',
    'editor_comment' => 'Kommentare',
    'editor_signature' => 'Signatur-Werkzeug',
    'editor_highlight' => 'Hervorheben',
    'editor_free_text' => 'Freitext',
    'editor_ink' => 'Zeichnen',
    'editor_stamp' => 'Stempel',
    'alt_text_settings' => 'Alt-Text-Einstellungen',
    'presentation' => 'Präsentationsmodus',
    'bookmark' => 'Lesezeichen',
    'first_last' => 'Erste / Letzte Seite',
    'rotation' => 'Drehen',
    'cursor_tools' => 'Cursor-Werkzeuge',
    'scroll_mode' => 'Scroll-Modus',
    'spread_mode' => 'Seiten-Anordnung',
    'document_properties' => 'Dokumenteigenschaften',
    'secondary_toolbar' => 'Zusatzwerkzeugleiste',
];

$presetDefinitions = [
    'full' => [],
    'balanced' => ['open_file', 'presentation', 'rotation', 'cursor_tools', 'scroll_mode', 'spread_mode', 'document_properties'],
    'compact' => ['sidebar', 'open_file', 'print', 'download', 'editor_tools', 'presentation', 'bookmark', 'first_last', 'rotation', 'cursor_tools', 'scroll_mode', 'spread_mode', 'document_properties', 'secondary_toolbar'],
    'custom' => [],
];

$profiles = $addon->getConfig('toolbar_profiles', []);
if (!is_array($profiles)) {
    $profiles = [];
}

$activeProfile = (string) $addon->getConfig('toolbar_active_profile', '');

$buildToolbarState = static function (string $preset, array $hiddenGroups): array {
    return [
        'preset' => $preset,
        'hidden_groups' => array_values(array_unique($hiddenGroups)),
    ];
};

$sanitizeProfileName = static function (string $profileName): string {
    $profileName = trim($profileName);
    $profileName = preg_replace('/[^a-zA-Z0-9_\-äöüÄÖÜß ]/u', '_', $profileName) ?? '';

    return trim($profileName);
};

$currentState = $buildToolbarState(
    (string) ($addon->getConfig('toolbar_preset', 'balanced') ?? 'balanced'),
    is_array($addon->getConfig('toolbar_hidden_groups', [])) ? $addon->getConfig('toolbar_hidden_groups', []) : []
);

if ('' !== $activeProfile && isset($profiles[$activeProfile]) && is_array($profiles[$activeProfile])) {
    $profileState = $profiles[$activeProfile];
    $currentState['preset'] = (string) ($profileState['preset'] ?? $currentState['preset']);
    $currentState['hidden_groups'] = is_array($profileState['hidden_groups'] ?? null) ? array_values($profileState['hidden_groups']) : $currentState['hidden_groups'];
}

$notice = '';
$error = '';

if (rex_post('toolbar-submit', 'bool')) {
    $preset = rex_post('toolbar_preset', 'string', 'balanced');
    if (!array_key_exists($preset, $presetDefinitions)) {
        $preset = 'balanced';
    }

    $hiddenGroups = [];
    if ($preset === 'custom') {
        $submittedGroups = rex_post('toolbar_hidden_groups', 'array', []);
        if (is_array($submittedGroups)) {
            foreach (array_keys($featureOptions) as $groupKey) {
                if (in_array($groupKey, $submittedGroups, true)) {
                    $hiddenGroups[] = $groupKey;
                }
            }
        }
    } else {
        $hiddenGroups = $presetDefinitions[$preset];
    }

    $action = rex_post('toolbar_action', 'string', 'save_default');

    if ('save_profile' === $action) {
        $profileName = $sanitizeProfileName(rex_post('toolbar_profile_name', 'string', ''));

        if ('' === $profileName) {
            $error = 'Bitte einen Profilnamen angeben.';
        } else {
            $profiles[$profileName] = $buildToolbarState($preset, $hiddenGroups);
            $addon->setConfig('toolbar_profiles', $profiles);
            $addon->setConfig('toolbar_active_profile', $profileName);
            $notice = 'Profil „' . $profileName . '“ wurde gespeichert und aktiviert.';
        }
    } elseif ('activate_profile' === $action) {
        $profileName = rex_post('toolbar_profile_selector', 'string', '');
        if ('' === $profileName || !isset($profiles[$profileName])) {
            $error = 'Bitte ein vorhandenes Profil auswählen.';
        } else {
            $addon->setConfig('toolbar_active_profile', $profileName);
            $currentState = $profiles[$profileName];
            $notice = 'Profil „' . $profileName . '“ ist jetzt aktiv.';
        }
    } elseif ('delete_profile' === $action) {
        $profileName = rex_post('toolbar_profile_selector', 'string', '');
        if ('' === $profileName || !isset($profiles[$profileName])) {
            $error = 'Bitte ein vorhandenes Profil zum Löschen auswählen.';
        } else {
            unset($profiles[$profileName]);
            $addon->setConfig('toolbar_profiles', $profiles);
            if ($activeProfile === $profileName) {
                $addon->setConfig('toolbar_active_profile', '');
                $activeProfile = '';
            }
            $notice = 'Profil „' . $profileName . '“ wurde gelöscht.';
        }
    } else {
        $addon->setConfig('toolbar_preset', $preset);
        $addon->setConfig('toolbar_hidden_groups', array_values(array_unique($hiddenGroups)));
        $currentState = $buildToolbarState($preset, $hiddenGroups);
        $notice = 'Toolbar-Konfiguration wurde gespeichert.';
    }
}

$config = $addon->getConfig();
$currentPreset = (string) ($currentState['preset'] ?? 'balanced');
$currentHiddenGroups = $currentState['hidden_groups'] ?? ($presetDefinitions[$currentPreset] ?? $presetDefinitions['balanced']);
if (!is_array($currentHiddenGroups)) {
    $currentHiddenGroups = [];
}

$viewerBaseUrl = rex_url::assets('addons/pdfout/vendor/web/viewer.html');
$demoPdf = 'compressed.tracemonkey-pldi-09.pdf';

$buildViewerUrl = static function (string $viewerBaseUrl, string $demoPdf, string $preset, array $hiddenGroups): string {
    $params = [
        'file' => $demoPdf,
        'toolbarPreset' => $preset,
        'toolbarHiddenGroups' => implode(',', $hiddenGroups),
        'viewerVersion' => '10.4.0',
    ];

    return $viewerBaseUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

$viewerUrl = $buildViewerUrl($viewerBaseUrl, $demoPdf, $currentPreset, $currentHiddenGroups);

$intro = '<div class="row"><div class="col-md-8"><h2>PDF.js Toolbar Builder</h2><p class="lead">' . rex_escape($addon->i18n('pdfout_toolbar_intro')) . '</p></div><div class="col-md-4 text-right"><a href="' . rex_escape($viewerUrl) . '" class="btn btn-primary btn-lg" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Vorschau öffnen</a></div></div>';

$toolbarBuilderUrl = $addon->getAssetsUrl('toolbar-builder.js') . '?v=' . rawurlencode((string) @filemtime($addon->getPath('assets/toolbar-builder.js')));
echo '<script src="' . rex_escape($toolbarBuilderUrl) . '"></script>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'PDF.js Toolbar');
$fragment->setVar('body', $intro, false);
echo $fragment->parse('core/page/section.php');

if ('' !== $notice) {
    echo rex_view::success($notice);
}

if ('' !== $error) {
    echo rex_view::error($error);
}

$form = '<form id="pdfout-toolbar-form" action="' . rex_url::currentBackendPage() . '" method="post" data-viewer-base-url="' . rex_escape($viewerBaseUrl) . '" data-demo-pdf="' . rex_escape($demoPdf) . '" data-viewer-preview-url="' . rex_escape($viewerUrl) . '">';
$form .= '<input type="hidden" name="toolbar-submit" value="1">';

$select = new rex_select();
$select->setName('toolbar_preset');
$select->setAttribute('class', 'form-control');
$select->addOption('Vollständig', 'full');
$select->addOption('Ausgewogen', 'balanced');
$select->addOption('Kompakt', 'compact');
$select->addOption('Benutzerdefiniert', 'custom');
$select->setSelected($currentPreset);

$form .= '<div class="row"><div class="col-md-6"><div class="panel panel-default"><div class="panel-heading"><h3 class="panel-title">' . rex_escape($addon->i18n('pdfout_toolbar_preset')) . '</h3></div><div class="panel-body">';
$form .= '<div class="form-group"><label for="toolbar_preset">Preset</label>' . $select->get() . '</div>';
$form .= '<div class="alert alert-info">Aktuell aktiv: <strong>' . rex_escape($currentPreset) . '</strong></div>';
$profileOptions = '<option value="">-- Profil wählen --</option>';
foreach ($profiles as $profileName => $profileConfig) {
    $profileOptions .= '<option value="' . rex_escape($profileName) . '"' . ($activeProfile === $profileName ? ' selected="selected"' : '') . '>' . rex_escape($profileName) . '</option>';
}
$form .= '<div class="form-group"><label for="toolbar_profile_selector">Gespeicherte Profile</label><select id="toolbar_profile_selector" name="toolbar_profile_selector" class="form-control">' . $profileOptions . '</select></div>';
$form .= '<div class="form-group"><label for="toolbar_profile_name">Profilname speichern</label><input type="text" id="toolbar_profile_name" name="toolbar_profile_name" class="form-control" placeholder="z.B. redaxo_lesemodus"></div>';
$form .= '<div class="btn-group" style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">';
$form .= '<button type="submit" name="toolbar_action" value="save_default" class="btn btn-primary"><i class="fa fa-save"></i> Toolbar speichern</button>';
$form .= '<button type="submit" name="toolbar_action" value="save_profile" class="btn btn-success"><i class="fa fa-bookmark"></i> Profil speichern</button>';
$form .= '<button type="submit" name="toolbar_action" value="activate_profile" class="btn btn-info"><i class="fa fa-check"></i> Profil aktivieren</button>';
$form .= '<button type="submit" name="toolbar_action" value="delete_profile" class="btn btn-danger"><i class="fa fa-trash"></i> Profil löschen</button>';
$form .= '</div>';
$form .= '</div></div></div>';

$form .= '<div class="col-md-6"><div class="panel panel-default"><div class="panel-heading"><h3 class="panel-title">' . rex_escape($addon->i18n('pdfout_toolbar_hidden_groups')) . '</h3></div><div class="panel-body">';
foreach ($featureOptions as $groupKey => $label) {
    $checked = in_array($groupKey, $currentHiddenGroups, true) ? ' checked="checked"' : '';
    $form .= '<div class="checkbox"><label><input type="checkbox" name="toolbar_hidden_groups[]" value="' . rex_escape($groupKey) . '"' . $checked . '> ' . rex_escape($label) . '</label></div>';
}
$form .= '</div></div></div></div>';

$form .= '<div class="row"><div class="col-md-12"><div class="panel panel-default"><div class="panel-heading"><h3 class="panel-title">' . rex_escape($addon->i18n('pdfout_toolbar_preview')) . '</h3></div><div class="panel-body">';
$form .= '<div class="embed-responsive embed-responsive-16by9" style="min-height: 760px; border: 1px solid #ddd;">';
$form .= '<iframe id="pdfout-toolbar-preview-frame" class="embed-responsive-item" src="' . rex_escape($viewerUrl) . '" style="width: 100%; height: 760px; border: 0;" loading="lazy"></iframe>';
$form .= '</div>';
$form .= '<p class="help-block" style="margin-top: 10px;">Die Vorschau lädt die mitgelieferte Demo-PDF <code>compressed.tracemonkey-pldi-09.pdf</code> aus dem PDF.js-Viewer.</p>';
$form .= '</div></div></div></div>';

$form .= '<div class="row"><div class="col-md-12 text-right"><button type="submit" class="btn btn-success btn-lg"><i class="fa fa-save"></i> ' . rex_escape($addon->i18n('pdfout_toolbar_save')) . '</button></div></div>';
$form .= '</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Toolbar-Konfigurator');
$fragment->setVar('body', $form, false);
echo $fragment->parse('core/page/section.php');