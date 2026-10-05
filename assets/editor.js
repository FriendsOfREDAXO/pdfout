/**
 * pdfout – PDF-Editor (Backend-Seite „Editor“)
 *
 * Lädt den pdf.js-Viewer im iframe mit Editor-Werkzeugen und speichert das bearbeitete PDF
 * über PDFViewerApplication.pdfDocument.saveDocument() per API (rex_api_pdfout_editor_save) in den Medienpool.
 */
(() => {
    const root = document.getElementById('pdfout-editor');
    if (!root || root.dataset.ready === '1') {
        return;
    }
    root.dataset.ready = '1';

    const frame = root.querySelector('iframe.pdfout-editor-frame');
    const status = document.getElementById('pdfout-editor-status');
    const filenameInput = document.getElementById('pdfout-editor-filename');
    const categorySelect = document.getElementById('pdfout-editor-category');
    const buttons = Array.from(root.querySelectorAll('[data-pdfout-save]'));
    const maxSize = parseInt(root.dataset.maxSize || '0', 10);

    let app = null;
    let busy = false;

    function setStatus(text, type = 'info', link = null) {
        status.className = 'pdfout-editor-status text-' + type;
        status.textContent = text;
        if (link) {
            link.forEach(({ href, label, target }) => {
                status.appendChild(document.createTextNode(' '));
                const a = document.createElement('a');
                a.href = href;
                a.textContent = label;
                if (target) {
                    a.target = target;
                    a.rel = 'noopener';
                }
                status.appendChild(a);
            });
        }
    }

    function setButtons(enabled) {
        buttons.forEach((button) => {
            button.disabled = !enabled;
        });
    }

    // Viewer-Optionen setzen, bevor pdf.js startet: das Ereignis „webviewerloaded“ geht an das Elterndokument.
    // disablePreferences verhindert, dass gespeicherte Einstellungen (localStorage) die Optionen überschreiben.
    document.addEventListener('webviewerloaded', (event) => {
        const source = event.detail && event.detail.source;
        if (!frame || source !== frame.contentWindow) {
            return;
        }
        const options = source.PDFViewerApplicationOptions;
        if (!options) {
            return;
        }
        options.set('disablePreferences', true);
        options.set('enableSignatureEditor', true);
        options.set('enableHighlightFloatingButton', true);
        options.set('annotationEditorMode', 0); // Editor aktiv, kein Werkzeug vorausgewählt
    });

    async function waitForDocument() {
        const win = frame.contentWindow;
        // PDFViewerApplication wird vom Viewer-Modul gesetzt; kurz warten, bis es existiert
        for (let i = 0; i < 200 && !(win && win.PDFViewerApplication); i++) {
            await new Promise((resolve) => setTimeout(resolve, 50));
        }
        const viewerApp = win && win.PDFViewerApplication;
        if (!viewerApp) {
            throw new Error('Viewer nicht verfügbar');
        }
        await viewerApp.initializedPromise;
        if (!viewerApp.pdfDocument) {
            await new Promise((resolve) => {
                viewerApp.eventBus.on('documentloaded', resolve, { once: true });
            });
        }
        return viewerApp;
    }

    function openViewer() {
        const viewer = new URL(root.dataset.viewer, document.baseURI);
        const file = new URL(root.dataset.file, document.baseURI);
        viewer.searchParams.set('file', file.pathname + file.search);
        // Editor-Werkzeuge bleiben sichtbar; nur Unpassendes ausblenden
        viewer.searchParams.set('toolbarHiddenGroups', 'open_file,presentation,document_properties');
        viewer.searchParams.set('viewerVersion', root.dataset.version || '');
        viewer.hash = 'zoom=page-width';

        frame.addEventListener('load', () => {
            waitForDocument()
                .then((viewerApp) => {
                    app = viewerApp;
                    setButtons(true);
                    setStatus('Bereit. Werkzeuge oben rechts im Viewer: Hervorheben, Text, Zeichnen, Bild, Unterschrift.');
                })
                .catch(() => {
                    setStatus('Der Viewer konnte das PDF nicht laden.', 'danger');
                });
        }, { once: true });

        frame.src = viewer.href;
    }

    async function save(mode) {
        if (busy || !app || !app.pdfDocument) {
            return;
        }
        const original = root.dataset.original;
        if (mode === 'replace' && !window.confirm('Original „' + original + '“ wirklich durch die bearbeitete Fassung ersetzen? Das lässt sich nicht rückgängig machen.')) {
            return;
        }

        busy = true;
        setButtons(false);
        setStatus('PDF wird gespeichert …');

        try {
            // laufende Eingabe (z. B. Freitext) abschließen, damit sie mitgespeichert wird
            const uiManager = app.pdfViewer && app.pdfViewer._layerProperties && app.pdfViewer._layerProperties.annotationEditorUIManager;
            if (uiManager && typeof uiManager.endCurrentEditing === 'function') {
                uiManager.endCurrentEditing();
            }
            const storage = app.pdfDocument.annotationStorage;
            if (!storage || storage.size === 0) {
                setStatus('Keine Änderungen vorhanden – zuerst etwas im PDF ergänzen.', 'warning');
                return;
            }

            const data = await app.pdfDocument.saveDocument();
            if (maxSize > 0 && data.byteLength > maxSize) {
                setStatus('Das bearbeitete PDF ist zu groß zum Speichern (' + Math.round(data.byteLength / 1048576) + ' MB).', 'danger');
                return;
            }

            const body = new FormData();
            body.append('pdf', new Blob([data], { type: 'application/pdf' }), 'editor.pdf');
            body.append('mode', mode);
            body.append('original', original);
            body.append('filename', filenameInput ? filenameInput.value : '');
            body.append('category_id', categorySelect ? categorySelect.value : '-1');

            const response = await fetch(root.dataset.saveUrl, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            let result = null;
            if ((response.headers.get('Content-Type') || '').includes('application/json')) {
                result = await response.json();
            }
            if (!result) {
                // z. B. abgelaufene Sitzung oder ungültiger CSRF-Token: REDAXO liefert dann die HTML-Seite
                setStatus('Speichern fehlgeschlagen (Sitzung abgelaufen oder Sicherheits-Token ungültig). Bitte Seite neu laden.', 'danger');
                return;
            }
            if (!result.ok) {
                setStatus('Speichern fehlgeschlagen: ' + result.message, 'danger');
                return;
            }

            const links = [{ href: result.url, label: 'Datei öffnen', target: '_blank' }];
            if (mode === 'new') {
                links.push({ href: result.editor, label: 'Neue Datei im Editor öffnen' });
            } else {
                links.push({ href: result.editor, label: 'Neu laden' });
            }
            setStatus(result.message, 'success', links);
        } catch (error) {
            setStatus('Speichern fehlgeschlagen: ' + (error && error.message ? error.message : error), 'danger');
        } finally {
            busy = false;
            setButtons(true);
        }
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => save(button.dataset.pdfoutSave));
    });

    if (frame) {
        openViewer();
    }
})();
