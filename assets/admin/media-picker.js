// Medien-Dialoge für alle Content-Redaktionseingaben (Seitenblöcke, Collections, Veranstaltungen,
// Vorlagen, Logo): „Bild auswählen“ zeigt wie in der Mediathek links den Ordnerbaum unter `/uploads`
// und rechts die Bilder des gewählten Ordners — inklusive Hochladen in den gewählten Ordner
// (dafür sind Mediathek-Bearbeitungsrechte nötig; ohne sie geht der Upload wie bisher in den
// Ordner `media`, siehe `LocalImageStorage`). Fest dimensioniertes Overlay mit eigenen
// Scrollbereichen für Baum und Bildraster. `openMediaFilePicker` bleibt die Dateiauswahl für
// Linkziele wie den Handlungsaufruf; sie steht allen offen, die Seiten bearbeiten dürfen.

import {element, field, formMessage, request, toast} from '../core.js';
import {emptyState, searchField, treeLayout} from './ui.js';
import {canEditModule} from './session.js';
import {ACCEPTED_IMAGE_TYPES, folderLabel, formatFileSize, pickFiles, slugifyFolderName} from './media.js';

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

/**
 * Bildauswahl mit Mediathek-Ordnerbaum. `onSelect(image)` übernimmt die gewählte Bilddatei;
 * `getUploadSource` liefert für den Upload die im Content-Formular gepflegte Bildquelle.
 */
const openImagePicker = async (onSelect, {getUploadSource = null} = {}) => {
    const editable = canEditModule('media_library');
    const dialog = mediaPickerDialog('Mediathek', 'Bild auswählen');
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
    const countLabel = element('span', {className: 'media-library-count', text: 'Bildauswahl wird geladen …'});
    const grid = element('div', {className: 'media-grid media-library-grid media-picker-grid', children: [emptyState('Bilder werden geladen …')]});
    const search = searchField('Bildname, Quelle oder Ordner suchen …', '', (value) => {
        searchTerm = value;
        renderContent();
    });
    const recursive = element('input', {attributes: {type: 'checkbox'}});
    recursive.addEventListener('change', () => {
        includeSubfolders = recursive.checked;
        reload();
    });
    const recurseToggle = element('label', {className: 'check-field', children: [recursive, element('span', {text: 'Unterordner einbeziehen'})]});

    const uploadButton = element('button', {className: 'button', text: editable ? '＋ Bild hier hochladen' : 'Bild hochladen', attributes: {type: 'button'}});
    // Bildquelle aus dem aufrufenden Formular mitliefern, damit sie wie beim bisherigen
    // Formular-Upload direkt in der Medienbibliothek landet.
    const uploadSource = () => (typeof getUploadSource === 'function' ? getUploadSource() : '') ?? '';
    uploadButton.addEventListener('click', async () => {
        const [file] = await pickFiles(ACCEPTED_IMAGE_TYPES);
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

    const content = element('section', {className: 'tree-content media-picker-content', attributes: {'aria-label': 'Bilder'}, children: [
        element('header', {className: 'tree-content-header media-picker-header', children: [
            element('div', {className: 'media-picker-info', children: [
                folderHeading,
                ...(editable ? [] : [element('small', {text: 'Ohne Mediathek-Recht wird der Upload immer im Ordner /uploads/media gespeichert.'})]),
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
            element('div', {className: 'media-card-preview', children: [element('img', {attributes: {src: image.url, alt: '', loading: 'lazy'}})]}),
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
        const images = files.filter((item) => item.isImage);
        const visible = images.filter((item) => !term
            || [item.originalName, item.url, item.source || '', item.folder].some((value) => value.toLowerCase().includes(term)));
        countLabel.textContent = `${visible.length} von ${images.length} Bildern`;
        grid.replaceChildren(...(loadError
            ? [emptyState(loadError)]
            : visible.length
                ? visible.map(imageCard)
                : [emptyState(images.length ? 'Kein Bild entspricht der Suche.' : `Der Ordner ${folderLabel(folder)} enthält keine Bilder.`)]));
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

const MEDIA_FILE_TYPE_LABEL = (file) => (file.url.split('.').pop() || 'Datei').toUpperCase();

// Dateiauswahl aus der Mediathek (alle Ordner) für Linkziele wie den Handlungsaufruf.
const openMediaFilePicker = async (onSelect) => {
    const dialog = mediaPickerDialog('Mediathek', 'Datei auswählen');
    const search = element('input', {attributes: {type: 'search', placeholder: 'Dateiname oder Ordner suchen …', 'aria-label': 'Dateiname oder Ordner suchen'}});
    const folder = element('select', {attributes: {'aria-label': 'Ordner'}, children: [element('option', {text: 'Alle Ordner', attributes: {value: ''}})]});
    const list = element('ul', {className: 'media-file-picker-list', children: [element('li', {text: 'Dateien werden geladen …'})]});
    dialog.append(
        element('div', {className: 'media-file-picker-toolbar', children: [search, folder]}),
        list,
    );
    document.body.append(dialog);
    dialog.showModal();
    search.focus();

    try {
        const [library, folders] = await Promise.all([
            request('/api/admin/v1/media-library?recursive=1'),
            request('/api/admin/v1/media-library/folders'),
        ]);
        folder.append(...folders.items.map((item) => element('option', {text: `/uploads/${item.path}`, attributes: {value: item.path}})));
        const render = () => {
            const term = search.value.trim().toLowerCase();
            const files = library.items.filter((file) => (!folder.value || file.folder === folder.value || file.folder.startsWith(`${folder.value}/`))
                && (!term || `${file.originalName} ${file.url}`.toLowerCase().includes(term)));
            list.replaceChildren(...(files.length ? files.map((file) => {
                const choose = element('button', {className: 'media-file-picker-item', attributes: {type: 'button'}, children: [
                    file.isImage
                        ? element('img', {attributes: {src: file.url, alt: '', loading: 'lazy'}})
                        : element('span', {className: 'media-file-picker-type', text: MEDIA_FILE_TYPE_LABEL(file)}),
                    element('span', {children: [
                        element('strong', {text: file.originalName}),
                        element('small', {text: file.url}),
                    ]}),
                ]});
                choose.addEventListener('click', () => {
                    onSelect(file);
                    dialog.close();
                });

                return element('li', {children: [choose]});
            }) : [element('li', {children: [emptyState(library.items.length ? 'Keine Datei passt zur Auswahl.' : 'Die Mediathek enthält noch keine Dateien.')]})]));
        };
        search.addEventListener('input', render);
        folder.addEventListener('change', render);
        render();
    } catch (error) {
        list.replaceChildren(element('li', {children: [emptyState(error.message)]}));
    }
};

export {openImagePicker, openMediaFilePicker};
