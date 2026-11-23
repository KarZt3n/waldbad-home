// Medien-Dialoge für alle Content-Redaktionseingaben (Seitenblöcke, Collections, Veranstaltungen,
// Vorlagen, Logo): „Bild auswählen“ bzw. „Datei auswählen“ (Linkziele wie der Handlungsaufruf)
// zeigen wie in der Mediathek links den Ordnerbaum unter `/uploads` und rechts die Bilder bzw.
// Dateien des gewählten Ordners — inklusive Hochladen in den gewählten Ordner (dafür sind
// Mediathek-Bearbeitungsrechte nötig; ohne sie gehen Bilder wie bisher in den Ordner `media`,
// siehe `LocalImageStorage`, beliebige Dateien lassen sich dann nicht hochladen). Fest
// dimensioniertes Overlay mit eigenen Scrollbereichen für Baum und Raster.

import {element, field, formMessage, request, toast} from '../core.js';
import {emptyState, searchField, treeLayout} from './ui.js';
import {canEditModule} from './session.js';
import {ACCEPTED_FILE_TYPES, ACCEPTED_IMAGE_TYPES, folderLabel, formatFileSize, pickFiles, slugifyFolderName} from './media.js';

const TREE_LAYOUT = {storageKey: 'waldbad.admin.tree-width.media-picker', defaultWidth: 'minmax(13rem, 17rem)'};

const mediaPickerDialog = (eyebrowText, title) => {
    const dialog = element('dialog', {className: 'media-dialog'});
    const close = element('button', {className: 'text-button', text: 'Schließen', attributes: {type: 'button'}});
    close.addEventListener('click', () => dialog.close());
    dialog.append(
        element('header', {className: 'media-dialog-header', children: [
            element('div', {children: [element('p', {className: 'eyebrow', text: eyebrowText}), element('h2', {text: title})]}),
            close,
        ]}),
    );
    dialog.addEventListener('close', () => dialog.remove());

    return dialog;
};

const mediaDetailsRow = (...parts) => element('small', {text: parts.filter(Boolean).join(' · ')});

const MEDIA_FILE_TYPE_LABEL = (file) => (file.url.split('.').pop() || 'Datei').toUpperCase();

const PICKER_TEXTS = {
    image: {title: 'Bild auswählen', plural: 'Bildern', loading: 'Bilder werden geladen …', search: 'Bildname, Quelle oder Ordner suchen …', upload: 'Bild', none: 'Kein Bild entspricht der Suche.', empty: 'keine Bilder', region: 'Bilder'},
    file: {title: 'Datei auswählen', plural: 'Dateien', loading: 'Dateien werden geladen …', search: 'Dateiname, Quelle oder Ordner suchen …', upload: 'Datei', none: 'Keine Datei entspricht der Suche.', empty: 'keine Dateien', region: 'Dateien'},
};

/**
 * Mediathek-Overlay mit Ordnerbaum. `kind` = `image` zeigt nur Bilder, `file` alle Dateien.
 * `onSelect(file)` übernimmt die Auswahl; `getUploadSource` liefert für den Upload die im
 * Content-Formular gepflegte Bildquelle.
 */
const openMediaPicker = async ({kind, onSelect, getUploadSource = null}) => {
    const texts = PICKER_TEXTS[kind];
    const imagesOnly = kind === 'image';
    const editable = canEditModule('media_library');
    const dialog = mediaPickerDialog('Mediathek', texts.title);
    dialog.classList.add('media-picker-dialog');
    const chooseImage = (image) => {
        dialog.close();
        onSelect(image);
    };

    let folder = 'media';
    let folders = [];
    let files = [];
    let loadError = null;
    let includeSubfolders = false;
    let searchTerm = '';

    const folderHeading = element('h3', {text: folderLabel(folder)});
    const countLabel = element('span', {className: 'media-library-count', text: texts.loading});
    const grid = element('div', {className: 'media-grid media-library-grid media-picker-grid', children: [emptyState(texts.loading)]});
    const search = searchField(texts.search, '', (value) => {
        searchTerm = value;
        renderContent();
    });
    const recursive = element('input', {attributes: {type: 'checkbox'}});
    recursive.addEventListener('change', () => {
        includeSubfolders = recursive.checked;
        reload();
    });
    const recurseToggle = element('label', {className: 'check-field', children: [recursive, element('span', {text: 'Unterordner einbeziehen'})]});

    const uploadButton = element('button', {className: 'button', text: editable ? `＋ ${texts.upload} hier hochladen` : `${texts.upload} hochladen`, attributes: {type: 'button'}});
    // Ohne Mediathek-Recht gibt es nur den Bild-Upload nach /uploads/media.
    uploadButton.hidden = !editable && !imagesOnly;
    // Bildquelle aus dem aufrufenden Formular mitliefern, damit sie wie beim bisherigen
    // Formular-Upload direkt in der Medienbibliothek landet.
    const uploadSource = () => (typeof getUploadSource === 'function' ? getUploadSource() : '') ?? '';
    uploadButton.addEventListener('click', async () => {
        const [file] = await pickFiles(imagesOnly ? ACCEPTED_IMAGE_TYPES : ACCEPTED_FILE_TYPES);
        if (!file) return;
        uploadButton.disabled = true;
        const body = new FormData();
        try {
            let stored;
            if (editable) {
                body.append('folder', folder);
                body.append('source', uploadSource());
                body.append('file', file);
                stored = await request('/api/admin/v1/media-library', {method: 'POST', body});
            } else {
                body.append('image', file);
                body.append('source', uploadSource());
                stored = await request('/api/admin/v1/media/images', {method: 'POST', body});
            }
            dialog.close();
            toast(`„${stored.originalName}“ wurde hochgeladen.`);
            onSelect(stored);
        } catch (error) {
            toast(error.message, 'error');
            uploadButton.disabled = false;
        }
    });

    /*
     * Unterordner anlegen (Mediathek-Berechtigung, Dialog-Ebene statt Rechtsklick, weil der
     * Kontextmenü-Ansatz an die Mediathek-Seite und nicht in den Overlay-Dialog gebunden ist).
     */
    const openCreateFolderDialog = (parentPath) => {
        const formDialog = element('dialog', {className: 'confirm-dialog'});
        const name = field('Ordnername', 'media-picker-folder-name');
        const input = name.querySelector('input');
        input.required = true;
        input.maxLength = 60;
        const preview = element('small', {text: `${folderLabel(parentPath)}/…`});
        input.addEventListener('input', () => {
            preview.textContent = `${folderLabel(parentPath)}/${slugifyFolderName(input.value) || '…'}`;
        });
        const message = formMessage();
        const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
        const submit = element('button', {className: 'button', text: 'Ordner anlegen', attributes: {type: 'submit'}});
        const form = element('form', {className: 'confirm-dialog-content', children: [
            element('p', {className: 'eyebrow', text: 'Neuer Ordner'}),
            element('h2', {text: `Ordner in ${folderLabel(parentPath)} anlegen`}),
            name,
            preview,
            element('small', {text: 'Der Name wird Teil der Datei-Adresse: Kleinbuchstaben, Ziffern und Bindestriche; Umlaute werden umgeschrieben.'}),
            message,
            element('div', {className: 'confirm-dialog-actions', children: [cancel, submit]}),
        ]});
        cancel.addEventListener('click', () => formDialog.close());
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            submit.disabled = true;
            try {
                const created = await request('/api/admin/v1/media-library/folders', {
                    method: 'POST',
                    body: JSON.stringify({parentPath, name: slugifyFolderName(input.value)}),
                });
                formDialog.close();
                toast(`Der Ordner ${folderLabel(created.path)} wurde angelegt.`);
                await reloadFolders(created.path);
                await reload();
            } catch (error) {
                message.textContent = error.message;
                submit.disabled = false;
            }
        });
        formDialog.addEventListener('close', () => formDialog.remove());
        formDialog.append(form);
        document.body.append(formDialog);
        formDialog.showModal();
        input.focus();
    };

    const headerActions = element('div', {className: 'media-picker-header-actions', children: [uploadButton]});
    if (editable) {
        const createFolderButton = element('button', {className: 'secondary-button', text: 'Unterordner …', attributes: {type: 'button'}});
        createFolderButton.addEventListener('click', () => openCreateFolderDialog(folder));
        headerActions.append(createFolderButton);
    }

    const content = element('section', {className: 'tree-content media-picker-content', attributes: {'aria-label': texts.region}, children: [
        element('header', {className: 'tree-content-header media-picker-header', children: [
            element('div', {className: 'media-picker-info', children: [
                folderHeading,
                ...(editable || !imagesOnly ? [] : [element('small', {text: 'Ohne Mediathek-Recht wird der Upload immer im Ordner /uploads/media gespeichert.'})]),
            ]}),
            headerActions,
        ]}),
        element('div', {className: 'media-library-toolbar', children: [search, recurseToggle, countLabel]}),
        grid,
    ]});
    const treePanel = element('aside', {className: 'tree-panel', attributes: {'aria-label': 'Mediathek-Ordner'}});
    dialog.append(treeLayout(treePanel, content, TREE_LAYOUT));
    document.body.append(dialog);
    dialog.showModal();

    const imageCard = (image) => {
        const choose = element('button', {className: 'secondary-button full', text: 'Auswählen', attributes: {type: 'button'}});
        choose.addEventListener('click', () => chooseImage(image));

        return element('article', {className: 'media-card', children: [
            element('div', {className: 'media-card-preview', children: [image.isImage
                ? element('img', {attributes: {src: image.url, alt: '', loading: 'lazy'}})
                : element('span', {className: 'media-file-type', text: MEDIA_FILE_TYPE_LABEL(image)})]}),
            element('strong', {text: image.originalName, attributes: {title: image.originalName}}),
            mediaDetailsRow(image.width && image.height ? `${image.width} × ${image.height} px` : null, formatFileSize(image.size)),
            ...(includeSubfolders ? [mediaDetailsRow(`📁 ${folderLabel(image.folder)}`)] : []),
            ...(image.source ? [element('small', {className: 'media-card-source', text: `Quelle: ${image.source}`})] : []),
            choose,
        ]});
    };

    const renderContent = () => {
        folderHeading.textContent = folderLabel(folder);
        const term = searchTerm.trim().toLowerCase();
        const images = imagesOnly ? files.filter((item) => item.isImage) : files;
        const visible = images.filter((item) => !term
            || [item.originalName, item.url, item.source || '', item.folder].some((value) => value.toLowerCase().includes(term)));
        countLabel.textContent = `${visible.length} von ${images.length} ${texts.plural}`;
        grid.replaceChildren(...(loadError
            ? [emptyState(loadError)]
            : visible.length
                ? visible.map(imageCard)
                : [emptyState(images.length ? texts.none : `Der Ordner ${folderLabel(folder)} enthält ${texts.empty}.`)]));
    };

    const renderTree = () => {
        const childrenOf = (parentPath) => folders.filter((entry) => entry.parentPath === parentPath);
        const node = (path, name, count) => {
            const button = element('button', {
                className: `tree-item${path === folder ? ' active' : ''}`,
                attributes: {type: 'button', title: folderLabel(path), ...(path === folder ? {'aria-current': 'true'} : {})},
                children: [
                    element('span', {className: 'tree-item-icon', text: path === folder ? '📂' : '📁', attributes: {'aria-hidden': 'true'}}),
                    element('span', {className: 'tree-item-name', text: name}),
                    ...(count ? [element('span', {className: 'tree-item-count', text: String(count)})] : []),
                ],
            });
            button.addEventListener('click', () => {
                folder = path;
                reload();
            });
            const children = childrenOf(path);

            return element('li', {children: [button, ...(children.length ? [element('ul', {children: children.map((child) => node(child.path, child.name, child.fileCount))})] : [])]});
        };

        treePanel.replaceChildren(element('ul', {children: [node('', 'uploads', 0)]}));
    };

    const reloadFolders = async (selectedPath) => {
        folders = (await request('/api/admin/v1/media-library/folders')).items;
        if (selectedPath !== undefined && selectedPath !== null) {
            folder = selectedPath;
        } else if (folder !== '' && !folders.some((entry) => entry.path === folder)) {
            // Der gewählte Startordner existiert (noch) nicht — mit dem Wurzelordner starten.
            folder = '';
        }
        renderTree();
    };

    const reload = async () => {
        loadError = null;
        const query = new URLSearchParams({folder, recursive: includeSubfolders ? '1' : '0'});
        try {
            files = (await request(`/api/admin/v1/media-library?${query}`)).items;
        } catch (error) {
            files = [];
            if (folder !== '') {
                // Der gewählte Ordner ist zwischenzeitlich weggefallen — zurück zum Wurzelordner.
                folder = '';
                await reloadFolders(folder);
                await reload();
                return;
            }
            loadError = error.message;
        }
        renderTree();
        renderContent();
    };

    try {
        await reloadFolders();
        await reload();
        search.querySelector('input').focus();
    } catch (error) {
        loadError = error.message;
        renderContent();
    }
};

const openImagePicker = (onSelect, {getUploadSource = null} = {}) => openMediaPicker({kind: 'image', onSelect, getUploadSource});

// Dateiauswahl für Linkziele wie den Handlungsaufruf — gleiches Overlay, aber alle Dateitypen.
const openMediaFilePicker = (onSelect) => openMediaPicker({kind: 'file', onSelect});

export {openImagePicker, openMediaFilePicker};
