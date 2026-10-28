// Modul „Mediathek“: Dateien unter `/uploads` in einer frei anlegbaren Ordnerstruktur (Baum links,
// Dateien des gewählten Ordners rechts, optional inklusive Unterordnern) mit Verwendungsnachweis
// (Seiten, Veranstaltungen, Vorlagen). Löschen entfernt die Verweise mit; Verschieben und Ersetzen
// eines Bilds stellen alle Verweise auf die neue URL um. Andere Dateien werden beim Ersetzen unter
// derselben URL überschrieben, damit Links darauf gültig bleiben.

import {confirmAction, element, field, formMessage, request, selectField, toast} from '../core.js';
import {emptyState, openContextMenu, openContextMenuAtElement, openContextMenuFromEvent, searchField, sectionHeading, treeLayout} from './ui.js';
import {canEditModule} from './session.js';
import {adminPath, setAdminPath, workspace} from './shell.js';

const ACCEPTED_FILE_TYPES = [
    'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'audio/mpeg', 'video/mp4',
    '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx', '.odt', '.ods',
].join(',');
const ACCEPTED_IMAGE_TYPES = 'image/jpeg,image/png,image/webp,image/gif';
const USAGE_AREAS = {
    page: {label: 'Seite', path: ['seiten']},
    event_schedule: {label: 'Veranstaltung', path: ['veranstaltungen', 'termine']},
    event_template: {label: 'Vorlage', path: ['veranstaltungen', 'vorlagen']},
};
const MEDIA_DRAG_TYPE = 'application/x-waldbad-media-url';
let currentFolder = '';
let includeSubfolders = false;
let searchTerm = '';
let usageFilter = 'all';

const folderLabel = (path) => `/uploads${path ? `/${path}` : ''}`;
const fileExtension = (url) => url.includes('.') ? url.slice(url.lastIndexOf('.') + 1).toLowerCase() : '';
const formatFileSize = (bytes) => bytes >= 1_000_000
    ? `${(bytes / 1_000_000).toLocaleString('de-DE', {maximumFractionDigits: 1})} MB`
    : `${Math.max(1, Math.round(bytes / 1000)).toLocaleString('de-DE')} KB`;
const usageCountLabel = (count) => count === 1 ? '1 Verwendung' : `${count} Verwendungen`;
const slugifyFolderName = (value) => value
    .trim()
    .toLowerCase()
    .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 60);

const usageList = (usages) => element('ul', {className: 'media-usage-list', children: usages.map((usage) => {
    const area = USAGE_AREAS[usage.area] || {label: usage.area, path: []};

    return element('li', {children: [
        element('span', {className: 'media-usage-area', text: area.label}),
        area.path.length
            ? element('a', {text: usage.title, attributes: {href: adminPath(...area.path)}})
            : element('span', {text: usage.title}),
        ...(usage.detail ? [element('small', {text: usage.detail})] : []),
    ]});
})});

const pickFiles = (accept, multiple = false) => new Promise((resolve) => {
    const input = element('input', {attributes: {type: 'file', accept, ...(multiple ? {multiple: 'multiple'} : {})}});
    input.addEventListener('change', () => resolve([...(input.files || [])]));
    input.addEventListener('cancel', () => resolve([]));
    input.click();
});

const openFolder = async (path) => {
    currentFolder = path;
    setAdminPath(['mediathek', ...path.split('/').filter(Boolean)]);
    await renderMediaLibrary();
};

const uploadFiles = async (button) => {
    const files = await pickFiles(ACCEPTED_FILE_TYPES, true);
    if (!files.length) return;
    button.disabled = true;
    let uploaded = 0;
    for (const file of files) {
        const body = new FormData();
        body.append('folder', currentFolder);
        body.append('file', file);
        try {
            await request('/api/admin/v1/media-library', {method: 'POST', body});
            uploaded += 1;
        } catch (error) {
            toast(`${file.name}: ${error.message}`, 'error');
        }
    }
    if (uploaded) toast(uploaded === 1 ? 'Die Datei wurde hochgeladen.' : `${uploaded} Dateien wurden hochgeladen.`);
    await renderMediaLibrary();
};

const replaceFile = async (item) => {
    const [file] = await pickFiles(item.isImage ? ACCEPTED_IMAGE_TYPES : `.${fileExtension(item.url)}`);
    if (!file) return;
    const description = item.isImage
        ? `Das Bild wird an ${item.usages.length === 1 ? 'einer Stelle' : `${item.usages.length} Stellen`} durch „${file.name}“ ersetzt. Alternativtexte und Bildquellen bleiben erhalten.`
        : `Die Datei wird durch „${file.name}“ überschrieben; ihre Adresse ${item.url} bleibt gleich.`;
    if ((item.usages.length || !item.isImage)
        && !(await confirmAction(`„${item.originalName}“ ersetzen?`, description, 'Ersetzen', item.usages.length ? [usageList(item.usages)] : []))) return;
    const body = new FormData();
    body.append('url', item.url);
    body.append('file', file);
    try {
        const result = await request('/api/admin/v1/media-library/replace', {method: 'POST', body});
        toast(result.updatedReferences
            ? `Die Datei wurde ersetzt und in ${result.updatedReferences} Einträgen aktualisiert.`
            : 'Die Datei wurde ersetzt.');
        await renderMediaLibrary();
    } catch (error) {
        toast(error.message, 'error');
    }
};

const moveFile = async (item, targetFolder) => {
    if (item.folder === targetFolder) return;
    try {
        const result = await request('/api/admin/v1/media-library/move', {
            method: 'POST',
            body: JSON.stringify({url: item.url, targetFolder}),
        });
        toast(result.updatedReferences
            ? `Nach ${folderLabel(targetFolder)} verschoben, ${result.updatedReferences} Einträge wurden angepasst.`
            : `Nach ${folderLabel(targetFolder)} verschoben.`);
        await renderMediaLibrary();
    } catch (error) {
        toast(error.message, 'error');
    }
};

const openMoveDialog = (item, folders) => {
    const dialog = element('dialog', {className: 'confirm-dialog'});
    const options = [['', folderLabel('')], ...folders.map((folder) => [folder.path, folderLabel(folder.path)])]
        .filter(([path]) => path !== item.folder);
    if (!options.length) {
        toast('Es gibt keinen anderen Ordner. Legen Sie zuerst einen Ordner an.', 'info');
        return;
    }
    const target = selectField('Zielordner', 'media-move-target', options, options[0][0]);
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    const submit = element('button', {className: 'button', text: 'Verschieben', attributes: {type: 'submit'}});
    const form = element('form', {className: 'confirm-dialog-content', children: [
        element('p', {className: 'eyebrow', text: 'Datei verschieben'}),
        element('h2', {text: item.originalName}),
        element('p', {text: item.usages.length
            ? `Die Datei wird an ${item.usages.length === 1 ? 'einer Stelle' : `${item.usages.length} Stellen`} verwendet; alle Verweise werden auf die neue Adresse umgestellt.`
            : 'Die Datei erhält im Zielordner eine neue Adresse.'}),
        target,
        ...(item.usages.length ? [usageList(item.usages)] : []),
        element('div', {className: 'confirm-dialog-actions', children: [cancel, submit]}),
    ]});
    cancel.addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        dialog.close();
        await moveFile(item, target.querySelector('select').value);
    });
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(form);
    document.body.append(dialog);
    dialog.showModal();
};

// Spiegelt `UploadDirectory::normalizedBasename()`, damit der Dialog die künftige Adresse zeigt.
const normalizeFilename = (value) => value
    .trim()
    .replace(/Ä/g, 'Ae').replace(/Ö/g, 'Oe').replace(/Ü/g, 'Ue')
    .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
    .toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 180) || 'datei';

const openRenameDialog = (item) => {
    const filename = item.url.slice(item.url.lastIndexOf('/') + 1);
    const extensionIndex = filename.lastIndexOf('.');
    const basename = extensionIndex > 0 ? filename.slice(0, extensionIndex) : filename;
    const extension = extensionIndex > 0 ? filename.slice(extensionIndex) : '';
    const directory = item.url.slice(0, item.url.lastIndexOf('/') + 1);
    const dialog = element('dialog', {className: 'confirm-dialog'});
    const name = field('Neuer Dateiname', 'media-rename-filename', basename);
    const input = name.querySelector('input');
    input.required = true;
    input.maxLength = 180;
    const preview = element('p', {className: 'media-rename-preview'});
    const updatePreview = () => {
        preview.textContent = `Neue Adresse: ${directory}${normalizeFilename(input.value)}${extension}`;
    };
    input.addEventListener('input', updatePreview);
    updatePreview();
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    const submit = element('button', {className: 'button', text: 'Umbenennen', attributes: {type: 'submit'}});
    const form = element('form', {className: 'confirm-dialog-content', children: [
        element('p', {className: 'eyebrow', text: 'Datei umbenennen'}),
        element('h2', {text: item.originalName}),
        element('p', {text: item.usages.length
            ? `Die Datei wird an ${item.usages.length === 1 ? 'einer Stelle' : `${item.usages.length} Stellen`} verwendet; alle Verweise werden auf die neue Adresse umgestellt. Die Endung ${extension || '(keine)'} bleibt erhalten.`
            : `Die Endung ${extension || '(keine)'} bleibt erhalten. Umlaute, Leerzeichen und Sonderzeichen werden umgewandelt.`}),
        name,
        preview,
        ...(item.usages.length ? [usageList(item.usages)] : []),
        element('div', {className: 'confirm-dialog-actions', children: [cancel, submit]}),
    ]});
    cancel.addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        try {
            const result = await request('/api/admin/v1/media-library/rename', {
                method: 'POST',
                body: JSON.stringify({url: item.url, filename: input.value}),
            });
            dialog.close();
            const newName = result.file.url.slice(result.file.url.lastIndexOf('/') + 1);
            toast(result.updatedReferences
                ? `In „${newName}“ umbenannt, ${result.updatedReferences} Einträge wurden angepasst.`
                : `In „${newName}“ umbenannt.`);
            await renderMediaLibrary();
        } catch (error) {
            submit.disabled = false;
            toast(error.message, 'error');
        }
    });
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(form);
    document.body.append(dialog);
    dialog.showModal();
    input.select();
};

const deleteFile = async (item) => {
    const inUse = item.usages.length > 0;
    const confirmed = await confirmAction(
        `„${item.originalName}“ löschen?`,
        inUse
            ? `Die Datei wird noch verwendet (${usageCountLabel(item.usages.length)}). Beim Löschen wird sie dort ebenfalls entfernt; reine Bildblöcke entfallen dabei ganz.`
            : 'Die Datei wird nirgends verwendet und dauerhaft gelöscht.',
        'Löschen',
        inUse ? [usageList(item.usages)] : [],
    );
    if (!confirmed) return;
    try {
        const result = await request('/api/admin/v1/media-library', {method: 'DELETE', body: JSON.stringify({url: item.url})});
        toast(result.removedReferences
            ? `Die Datei wurde gelöscht und aus ${result.removedReferences} Einträgen entfernt.`
            : 'Die Datei wurde gelöscht.');
        await renderMediaLibrary();
    } catch (error) {
        toast(error.message, 'error');
    }
};

const openCreateFolderDialog = (parentPath) => {
    const dialog = element('dialog', {className: 'confirm-dialog'});
    const name = field('Ordnername', 'media-folder-name');
    const input = name.querySelector('input');
    input.required = true;
    input.maxLength = 60;
    const preview = element('small', {text: `${folderLabel(parentPath)}/…`});
    const message = formMessage();
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    const submit = element('button', {className: 'button', text: 'Ordner anlegen', attributes: {type: 'submit'}});
    input.addEventListener('input', () => {
        preview.textContent = `${folderLabel(parentPath)}/${slugifyFolderName(input.value) || '…'}`;
    });
    const form = element('form', {className: 'confirm-dialog-content', children: [
        element('p', {className: 'eyebrow', text: 'Neuer Ordner'}),
        element('h2', {text: `Ordner in ${folderLabel(parentPath)} anlegen`}),
        name,
        preview,
        element('small', {text: 'Der Name wird Teil der Datei-Adresse: Kleinbuchstaben, Ziffern und Bindestriche; Umlaute werden umgeschrieben.'}),
        message,
        element('div', {className: 'confirm-dialog-actions', children: [cancel, submit]}),
    ]});
    cancel.addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        try {
            const folder = await request('/api/admin/v1/media-library/folders', {
                method: 'POST',
                body: JSON.stringify({parentPath, name: slugifyFolderName(input.value)}),
            });
            toast(`Der Ordner ${folderLabel(folder.path)} wurde angelegt.`);
            dialog.close();
            await openFolder(folder.path);
        } catch (error) {
            message.textContent = error.message;
            submit.disabled = false;
        }
    });
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(form);
    document.body.append(dialog);
    dialog.showModal();
    input.focus();
};

const deleteFolder = async (path) => {
    if (!(await confirmAction(`Ordner ${folderLabel(path)} löschen?`, 'Nur leere Ordner ohne Unterordner können gelöscht werden.', 'Löschen'))) return;
    try {
        await request('/api/admin/v1/media-library/folders', {method: 'DELETE', body: JSON.stringify({path})});
        toast(`Der Ordner ${folderLabel(path)} wurde gelöscht.`);
        const parentPath = path.includes('/') ? path.slice(0, path.lastIndexOf('/')) : '';
        await (currentFolder === path || currentFolder.startsWith(`${path}/`) ? openFolder(parentPath) : renderMediaLibrary());
    } catch (error) {
        toast(error.message, 'error');
    }
};

const folderMenuItems = (path) => [
    {label: 'Öffnen', run: () => openFolder(path)},
    {label: 'Neuer Unterordner …', run: () => openCreateFolderDialog(path)},
    ...(path === '' ? [] : [{
        label: 'Ordner löschen',
        danger: true,
        disabled: path === 'media' ? 'Der Standardordner für Uploads aus dem Seiteneditor bleibt bestehen.' : null,
        run: () => deleteFolder(path),
    }]),
];

const folderTree = (folders, items, editable) => {
    const itemByUrl = new Map(items.map((item) => [item.url, item]));
    const childrenOf = (parentPath) => folders.filter((folder) => folder.parentPath === parentPath);
    const node = (path, label, count) => {
        const button = element('button', {
            className: `tree-item${path === currentFolder ? ' active' : ''}`,
            attributes: {type: 'button', ...(path === currentFolder ? {'aria-current': 'true'} : {})},
            children: [
                element('span', {className: 'tree-item-icon', text: path === currentFolder ? '📂' : '📁', attributes: {'aria-hidden': 'true'}}),
                element('span', {className: 'tree-item-name', text: label}),
                ...(count ? [element('span', {className: 'tree-item-count', text: String(count)})] : []),
            ],
        });
        button.addEventListener('click', () => openFolder(path));
        if (editable) {
            button.addEventListener('contextmenu', (event) => openContextMenuFromEvent(event, button, folderMenuItems(path)));
            button.addEventListener('dragover', (event) => {
                if (!event.dataTransfer.types.includes(MEDIA_DRAG_TYPE)) return;
                event.preventDefault();
                button.classList.add('is-drop-target');
            });
            button.addEventListener('dragleave', () => button.classList.remove('is-drop-target'));
            button.addEventListener('drop', async (event) => {
                event.preventDefault();
                button.classList.remove('is-drop-target');
                const item = itemByUrl.get(event.dataTransfer.getData(MEDIA_DRAG_TYPE));
                if (item) await moveFile(item, path);
            });
        }
        const children = childrenOf(path);

        return element('li', {children: [
            button,
            ...(children.length ? [element('ul', {children: children.map((child) => node(child.path, child.name, child.fileCount))})] : []),
        ]});
    };

    return element('aside', {className: 'tree-panel', attributes: {'aria-label': 'Ordner'}, children: [
        element('ul', {children: [node('', 'uploads', 0)]}),
        ...(editable ? [element('small', {text: 'Rechtsklick auf einen Ordner: neuer Unterordner oder löschen. Dateien per Drag & Drop auf einen Ordner verschieben.'})] : []),
    ]});
};

const mediaCard = (item, folders, editable) => {
    const inUse = item.usages.length > 0;
    const preview = item.isImage
        ? element('img', {attributes: {src: item.url, alt: '', loading: 'lazy'}})
        : element('span', {className: 'media-file-type', text: fileExtension(item.url).toUpperCase() || 'DATEI'});
    const details = [
        item.width && item.height ? `${item.width} × ${item.height} px` : null,
        formatFileSize(item.size),
    ].filter(Boolean).join(' · ');
    const fileMenuItems = () => [
        {label: 'Öffnen', run: () => window.open(item.url, '_blank', 'noopener')},
        {label: 'Adresse kopieren', run: async () => {
            try {
                await navigator.clipboard.writeText(item.url);
                toast('Die Adresse wurde kopiert.');
            } catch {
                toast(item.url, 'info');
            }
        }},
        ...(editable ? [
            {label: item.isImage ? 'Ersetzen …' : 'Ersetzen (gleiche Adresse) …', run: () => replaceFile(item)},
            {label: 'Umbenennen …', run: () => openRenameDialog(item)},
            {label: 'Verschieben …', run: () => openMoveDialog(item, folders)},
            {label: 'Löschen', danger: true, run: () => deleteFile(item)},
        ] : []),
    ];
    const menuButton = element('button', {
        className: 'media-card-menu-button',
        text: '☰',
        attributes: {type: 'button', 'aria-label': `Aktionen für ${item.originalName}`, 'aria-haspopup': 'menu'},
    });
    menuButton.addEventListener('click', (event) => {
        event.stopPropagation();
        openContextMenuAtElement(menuButton, fileMenuItems());
    });
    const children = [
        element('div', {className: 'media-card-preview', children: [
            element('a', {className: 'media-library-preview', attributes: {href: item.url, target: '_blank', rel: 'noopener'}, children: [preview]}),
            menuButton,
        ]}),
        element('strong', {text: item.originalName, attributes: {title: item.originalName}}),
        element('small', {text: details}),
        ...(includeSubfolders ? [element('small', {text: `📁 ${folderLabel(item.folder)}`})] : []),
        ...(item.source ? [element('small', {className: 'media-card-source', text: `Quelle: ${item.source}`})] : []),
        inUse
            ? element('details', {className: 'media-usage', children: [
                element('summary', {children: [element('span', {className: 'status-badge status-active', text: `In Verwendung · ${usageCountLabel(item.usages.length)}`})]}),
                usageList(item.usages),
            ]})
            : element('span', {className: 'status-badge status-inactive', text: 'Nicht verwendet'}),
    ];
    const card = element('article', {className: `media-card${inUse ? '' : ' is-unused'}`, children});
    card.addEventListener('contextmenu', (event) => {
        if (event.target.closest('a[href]:not(.media-library-preview)')) return;
        event.preventDefault();
        openContextMenu(event.clientX || menuButton.getBoundingClientRect().left, event.clientY || menuButton.getBoundingClientRect().bottom, fileMenuItems(), menuButton);
    });
    if (editable) {
        card.draggable = true;
        card.addEventListener('dragstart', (event) => {
            event.dataTransfer.setData(MEDIA_DRAG_TYPE, item.url);
            event.dataTransfer.effectAllowed = 'move';
            // Statt der großen Karte zieht ein kleiner runder Platzhalter mit, damit die Ordner sichtbar bleiben.
            const ghost = element('div', {className: 'media-drag-ghost', children: [
                item.isImage
                    ? element('img', {attributes: {src: item.url, alt: ''}})
                    : element('span', {text: fileExtension(item.url).toUpperCase() || 'DATEI'}),
            ]});
            document.body.append(ghost);
            event.dataTransfer.setDragImage(ghost, 28, 28);
            requestAnimationFrame(() => ghost.remove());
            card.classList.add('is-dragging');
        });
        card.addEventListener('dragend', () => card.classList.remove('is-dragging'));
    }

    return card;
};

const renderMediaLibrary = async () => {
    const query = new URLSearchParams({folder: currentFolder, recursive: includeSubfolders ? '1' : '0'});
    let folderData;
    let data;
    try {
        [folderData, data] = await Promise.all([
            request('/api/admin/v1/media-library/folders'),
            request(`/api/admin/v1/media-library?${query}`),
        ]);
    } catch (error) {
        if (currentFolder === '') throw error;
        toast(error.message, 'error');
        await openFolder('');
        return;
    }
    const folders = folderData.items;
    const editable = canEditModule('media_library');

    const heading = sectionHeading('Mediathek', 'Dateien in Ordnern verwalten, ersetzen, verschieben und sehen, wo sie verwendet werden');
    if (editable) {
        const upload = element('button', {className: 'button', text: '＋ Dateien hochladen', attributes: {type: 'button'}});
        upload.addEventListener('click', () => uploadFiles(upload));
        heading.append(upload);
    }

    const grid = element('div', {className: 'media-grid media-library-grid'});
    const countLabel = element('span', {className: 'media-library-count'});
    const render = () => {
        const term = searchTerm.toLowerCase();
        const visible = data.items.filter((item) => (usageFilter === 'all'
            || (usageFilter === 'used' ? item.usages.length > 0 : item.usages.length === 0))
            && (!term || [item.originalName, item.url, item.source || '', ...item.usages.map((usage) => usage.title)]
                .some((value) => value.toLowerCase().includes(term))));
        countLabel.textContent = `${visible.length} von ${data.items.length} Dateien`;
        grid.replaceChildren(...(visible.length
            ? visible.map((item) => mediaCard(item, folders, editable))
            : [emptyState(data.items.length ? 'Keine Dateien für diese Auswahl.' : 'Dieser Ordner enthält keine Dateien.')]));
    };
    const recursive = element('input', {attributes: {type: 'checkbox'}});
    recursive.checked = includeSubfolders;
    recursive.addEventListener('change', async () => {
        includeSubfolders = recursive.checked;
        await renderMediaLibrary();
    });
    const search = searchField('Dateiname, Quelle oder Verwendung suchen …', searchTerm, (value) => {
        searchTerm = value;
        render();
    });
    const filter = selectField('Verwendung', 'media-usage-filter', [
        ['all', 'Alle Dateien'],
        ['used', 'In Verwendung'],
        ['unused', 'Nicht verwendet'],
    ], usageFilter);
    filter.querySelector('select').addEventListener('change', (event) => {
        usageFilter = event.target.value;
        render();
    });
    render();

    workspace.replaceChildren(
        heading,
        treeLayout(
            folderTree(folders, data.items, editable),
            element('section', {className: 'tree-content', attributes: {'aria-label': 'Dateien'}, children: [
                element('header', {className: 'tree-content-header', children: [
                    element('h3', {text: folderLabel(currentFolder)}),
                    element('label', {className: 'check-field', children: [recursive, element('span', {text: 'Unterordner einbeziehen'})]}),
                ]}),
                element('div', {className: 'media-library-toolbar', children: [search, filter, countLabel]}),
                grid,
            ]}),
            {storageKey: 'waldbad.admin.tree-width.media', defaultWidth: 'minmax(13rem, 17rem)'},
        ),
    );
};

const showMediaLibrary = async (segments = []) => {
    currentFolder = segments[0] === 'mediathek' ? segments.slice(1).join('/') : '';
    await renderMediaLibrary();
};

export {showMediaLibrary};
