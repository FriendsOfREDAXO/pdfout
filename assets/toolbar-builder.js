(() => {
    const PRESET_GROUPS = {
        full: [],
        balanced: ['open_file', 'presentation', 'rotation', 'cursor_tools', 'scroll_mode', 'spread_mode', 'document_properties'],
        compact: ['sidebar', 'open_file', 'print', 'download', 'editor_tools', 'presentation', 'bookmark', 'first_last', 'rotation', 'cursor_tools', 'scroll_mode', 'spread_mode', 'document_properties', 'secondary_toolbar'],
        custom: [],
    };

    const GROUP_TO_CHECKBOX = {
        sidebar: 'sidebar',
        search: 'search',
        navigation: 'navigation',
        page_number: 'page_number',
        zoom: 'zoom',
        open_file: 'open_file',
        print: 'print',
        download: 'download',
        presentation: 'presentation',
        bookmark: 'bookmark',
        first_last: 'first_last',
        rotation: 'rotation',
        cursor_tools: 'cursor_tools',
        scroll_mode: 'scroll_mode',
        spread_mode: 'spread_mode',
        document_properties: 'document_properties',
        secondary_toolbar: 'secondary_toolbar',
    };

    function getForm() {
        return document.getElementById('pdfout-toolbar-form');
    }

    function getPreviewFrame() {
        return document.getElementById('pdfout-toolbar-preview-frame');
    }

    function getPresetSelect(form) {
        return form ? form.querySelector('select[name="toolbar_preset"]') : null;
    }

    function getCheckboxes(form) {
        return form ? Array.from(form.querySelectorAll('input[name="toolbar_hidden_groups[]"]')) : [];
    }

    function setCheckboxState(form, groupKeys) {
        const selected = new Set(groupKeys);
        getCheckboxes(form).forEach((checkbox) => {
            checkbox.checked = selected.has(checkbox.value);
        });
    }

    function readHiddenGroups(form) {
        return getCheckboxes(form)
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.value);
    }

    function buildPreviewUrl(form) {
        const baseUrl = form.dataset.viewerBaseUrl;
        const demoPdf = form.dataset.demoPdf;
        const presetSelect = getPresetSelect(form);
        const preset = presetSelect ? presetSelect.value : 'balanced';
        const hiddenGroups = readHiddenGroups(form);

        const params = new URLSearchParams();
        params.set('file', demoPdf);
        params.set('toolbarPreset', preset);
        params.set('toolbarHiddenGroups', hiddenGroups.join(','));
        params.set('previewNonce', Date.now().toString(36));

        return `${baseUrl}?${params.toString()}`;
    }

    function updatePreview(form) {
        const frame = getPreviewFrame();
        if (!frame) {
            return;
        }

        const previewUrl = buildPreviewUrl(form);
        frame.src = previewUrl;
        form.dataset.viewerPreviewUrl = previewUrl;
    }

    function syncPresetToCheckboxes(form) {
        const presetSelect = getPresetSelect(form);
        if (!presetSelect) {
            return;
        }

        const preset = presetSelect.value;
        if (!Object.prototype.hasOwnProperty.call(PRESET_GROUPS, preset)) {
            return;
        }

        setCheckboxState(form, PRESET_GROUPS[preset]);
    }

    function syncPresetFromCheckboxes(form) {
        const presetSelect = getPresetSelect(form);
        if (!presetSelect) {
            return;
        }

        const currentHiddenGroups = readHiddenGroups(form).slice().sort().join(',');
        const matchingPreset = Object.entries(PRESET_GROUPS).find(([, groups]) => groups.slice().sort().join(',') === currentHiddenGroups);

        if (matchingPreset) {
            presetSelect.value = matchingPreset[0];
        } else {
            presetSelect.value = 'custom';
        }
    }

    document.addEventListener('change', (event) => {
        const form = getForm();
        if (!form || !event.target || !form.contains(event.target)) {
            return;
        }

        if (event.target.matches('select[name="toolbar_preset"]')) {
            syncPresetToCheckboxes(form);
            updatePreview(form);
            return;
        }

        if (event.target.matches('input[name="toolbar_hidden_groups[]"]')) {
            syncPresetFromCheckboxes(form);
            updatePreview(form);
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        const form = getForm();
        if (!form) {
            return;
        }

        updatePreview(form);
    });
})();