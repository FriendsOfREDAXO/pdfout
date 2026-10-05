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

    /**
     * Rücksprung-Knopf: ?returnUrl=<Adresse>&returnLabel=<Text> zeigt links in der Leiste „← Zurück“.
     * Gedacht für den Viewer als eigene Seite (z. B. auf iPhone/iPad). Nur Adressen derselben Domain.
     */
    function readReturnTarget() {
        try {
            const params = new URL(window.location.href).searchParams;
            const raw = params.get('returnUrl');
            if (!raw) {
                return null;
            }
            const target = new URL(raw, window.location.href);
            if (target.origin !== window.location.origin || !/^https?:$/.test(target.protocol)) {
                return null;
            }
            const label = (params.get('returnLabel') || '').trim().slice(0, 40) || (/^de\b/i.test(navigator.language || '') ? 'Zurück' : 'Back');
            return { url: target.href, label };
        } catch (error) {
            return null;
        }
    }

    function addReturnButton(target) {
        const container = document.getElementById('toolbarViewerLeft');
        if (!container || document.getElementById('pdfoutReturn')) {
            return;
        }
        const link = document.createElement('a');
        link.id = 'pdfoutReturn';
        link.href = target.url;
        // Stile direkt am Element: eingefügte <style>-Blöcke blockiert eine strenge Content-Security-Policy
        Object.assign(link.style, {
            display: 'inline-flex',
            alignItems: 'center',
            gap: '3px',
            flex: 'none',
            height: '28px',
            margin: '2px 4px 2px 2px',
            padding: '0 8px 0 4px',
            borderRadius: '4px',
            color: 'var(--toolbar-icon-bg-color, var(--main-color, #0c0c0d))',
            background: 'var(--button-hover-color, rgba(0, 0, 0, 0.07))',
            fontSize: '13px',
            lineHeight: '28px',
            textDecoration: 'none',
            whiteSpace: 'nowrap',
        });
        link.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        link.appendChild(document.createTextNode(target.label));
        link.addEventListener('click', (event) => {
            // kam der Besuch von genau dieser Seite: zurück im Verlauf (Scrollposition bleibt erhalten)
            let fromTarget = false;
            try {
                fromTarget = document.referrer !== '' && new URL(document.referrer).href.split('#')[0] === target.url.split('#')[0];
            } catch (error) {
                fromTarget = false;
            }
            if (fromTarget && window.history.length > 1) {
                event.preventDefault();
                window.history.back();
            }
        });
        container.insertBefore(link, container.firstChild);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const state = readToolbarState();
        const hiddenGroups = Array.isArray(state.hiddenGroups) ? state.hiddenGroups : [];

        scheduleHiddenGroups(hiddenGroups);

        const returnTarget = readReturnTarget();
        if (returnTarget) {
            addReturnButton(returnTarget);
        }
    });
})();