(() => {
    const FEATURE_MAP = {
        sidebar: ['viewsManagerToggleButton', 'viewsManager'],
        search: ['viewFindButton', 'findbar'],
        navigation: ['previous', 'next'],
        page_number: ['pageNumber', 'numPages'],
        zoom: ['zoomOutButton', 'zoomInButton', 'scaleSelectContainer'],
        open_file: ['secondaryOpenFile'],
        print: ['printButton', 'secondaryPrint'],
        download: ['downloadButton', 'secondaryDownload'],
        editor_tools: ['editorModeButtons', 'editorModeSeparator'],
        editor_comment: ['editorComment'],
        editor_signature: ['editorSignature'],
        editor_highlight: ['editorHighlight'],
        editor_free_text: ['editorFreeText'],
        editor_ink: ['editorInk'],
        editor_stamp: ['editorStamp'],
        alt_text_settings: ['imageAltTextSettingsSeparator', 'imageAltTextSettings'],
        presentation: ['presentationMode'],
        bookmark: ['viewBookmark', 'viewBookmarkSeparator'],
        first_last: ['firstPage', 'lastPage'],
        rotation: ['pageRotateCw', 'pageRotateCcw'],
        cursor_tools: ['cursorToolButtons'],
        scroll_mode: ['scrollModeButtons'],
        spread_mode: ['spreadModeButtons'],
        document_properties: ['documentProperties'],
        secondary_toolbar: ['secondaryToolbarToggle', 'secondaryToolbar'],
    };

    function readToolbarState() {
        try {
            const currentUrl = new URL(window.location.href);

            return {
                hiddenGroups: (currentUrl.searchParams.get('toolbarHiddenGroups') || '')
                    .split(',')
                    .map((group) => group.trim())
                    .filter(Boolean),
            };
        } catch (error) {
            return { hiddenGroups: [] };
        }
    }

    function hideElementById(id) {
        const element = document.getElementById(id);
        if (element) {
            element.hidden = true;
            element.classList.add('hidden');
        }
    }

    function applyHiddenGroups(hiddenGroups) {
        hiddenGroups.forEach((group) => {
            const elementIds = FEATURE_MAP[group] || [];
            elementIds.forEach(hideElementById);
        });
    }

    function scheduleHiddenGroups(hiddenGroups) {
        const apply = () => applyHiddenGroups(hiddenGroups);

        apply();

        window.addEventListener('DOMContentLoaded', apply, { once: true });
        window.addEventListener('load', apply, { once: true });

        window.setTimeout(apply, 0);
        window.setTimeout(apply, 250);
        window.setTimeout(apply, 1000);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const state = readToolbarState();
        const hiddenGroups = Array.isArray(state.hiddenGroups) ? state.hiddenGroups : [];

        scheduleHiddenGroups(hiddenGroups);
    });
})();