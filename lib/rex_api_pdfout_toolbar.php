<?php

class rex_api_pdfout_toolbar extends rex_api_function
{
    protected $published = true;

    public function execute()
    {
        rex_response::cleanOutputBuffers();

        $addon = rex_addon::get('pdfout');
        $preset = (string) $addon->getConfig('toolbar_preset', 'balanced');

        $hiddenGroups = $addon->getConfig('toolbar_hidden_groups', []);
        if (!is_array($hiddenGroups)) {
            $hiddenGroups = [];
        }

        rex_response::sendJson([
            'preset' => $preset,
            'hiddenGroups' => array_values($hiddenGroups),
        ]);
        exit;
    }
}