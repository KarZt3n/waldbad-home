// Modul „Seiten“: Seitenbaum (Drag & Drop), Block-Editor (Rich-Text, Bilder, Collections,
// Veranstaltungsblöcke, Erweiterungen) sowie die Entwurfsvorschau, die über `content-rendering.js`
// exakt denselben Rendering-Pfad wie die öffentliche Website nutzt.

import {buildPageTree, confirmAction, element, field, fieldRow, flattenPageTree, request, toast} from '../core.js';
import {emptyState, sectionHeading} from './ui.js';
import {canEditPages, canManagePageStructure, canPublishPages} from './session.js';
import {renderContentCard} from '../content-rendering.js';
import {workspace} from './shell.js';

const BLOCK_TYPES = {
    heading: 'Überschrift',
    rich_text: 'Text',
    image: 'Bild',
    image_text: 'Bild + Text',
    feature_collection: 'Collection: Bild + Text',
    alert: 'Hinweis',
    call_to_action: 'Handlungsaufruf',
    custom_html: 'Eigenes HTML',
    embedded_page: 'Seite einbetten',
    page_teaser: 'Seitenteaser',
    event: 'Veranstaltung',
    event_reference: 'Veranstaltung einbetten',
    extension: 'Erweiterung',
};
const createCollectionItem = (title = '') => ({
    title,
    content: '',
    mediaUrl: null,
    mediaAlt: null,
    mediaSource: null,
});

const createBlock = (type) => ({
    type,
    content: '',
    mediaUrl: null,
    mediaAlt: null,
    mediaSource: null,
    linkUrl: null,
    linkLabel: null,
    layout: ['image_text', 'page_teaser', 'event_reference'].includes(type) ? 'image_left' : (type === 'image' ? 'center' : null),
    imageWidthPercent: ['image_text', 'page_teaser', 'event_reference'].includes(type) ? 50 : (type === 'image' ? 100 : null),
    verticalAlignment: ['image_text', 'page_teaser', 'event_reference'].includes(type) ? 'center' : null,
    textAlignment: ['image_text', 'page_teaser', 'event_reference'].includes(type) ? 'left' : null,
    imageFit: ['image_text', 'page_teaser', 'event_reference'].includes(type) ? 'cover' : null,
    embeddedPageId: null,
    eventTitle: type === 'event' ? '' : null,
    eventDate: type === 'event' ? '' : null,
    eventTime: type === 'event' ? '14:00' : null,
    eventIdentifier: type === 'event' ? crypto.randomUUID() : null,
    eventHelpEnabled: type === 'event',
    eventHelpButtonLabel: type === 'event' ? 'Ich möchte helfen!' : null,
    eventActivities: [],
    eventCallToActions: [],
    extensionKey: type === 'extension' ? 'membership_application' : null,
    collectionColumns: type === 'feature_collection' ? 3 : null,
    collectionItems: [],
});
const slugify = (value) => value
    .trim()
    .toLocaleLowerCase('de-DE')
    .replaceAll('ä', 'ae')
    .replaceAll('ö', 'oe')
    .replaceAll('ü', 'ue')
    .replaceAll('ß', 'ss')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
const hierarchicalSlug = (title, parentId, pages) => {
    const leafSlug = slugify(title);
    if (!leafSlug) return '';

    const parent = pages.find((candidate) => candidate.id === parentId);
    return parent ? `${parent.slug}/${leafSlug}` : leafSlug;
};
const parentPageField = (pages, page, initialParentId) => {
    const select = element('select', {attributes: {name: 'parentId', id: 'parentId'}});
    select.append(element('option', {text: 'Keine – Hauptseite', attributes: {value: ''}}));

    const childIds = new Set();
    const collectChildren = (parentId) => pages.filter((candidate) => candidate.parentId === parentId).forEach((child) => {
        childIds.add(child.id);
        collectChildren(child.id);
    });
    if (page) collectChildren(page.id);

    flattenPageTree(buildPageTree(pages)).forEach(({page: candidate, depth}) => {
        if (candidate.id === page?.id || childIds.has(candidate.id)) return;
        select.append(element('option', {
            text: `${'— '.repeat(depth)}${candidate.title}`,
            attributes: {value: candidate.id},
        }));
    });
    select.value = page?.parentId || initialParentId || '';

    return element('label', {className: 'field', children: [element('span', {text: 'Übergeordnete Seite'}), select]});
};
const richTextEditor = (block, index, onChange = null, ariaLabel = 'Rich-Text-Inhalt') => {
    const editor = element('div', {
        className: 'rich-text-surface',
        attributes: {contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-label': ariaLabel},
    });
    editor.innerHTML = block.content || '';
    const source = element('textarea', {
        className: 'html-source',
        attributes: {id: 'block-content-' + index, 'aria-label': 'HTML-Quelltext'},
    });
    source.hidden = true;

    const syncVisual = () => {
        block.content = editor.innerHTML;
        onChange?.(block.content);
    };
    const run = (command, value = null) => {
        editor.focus();
        document.execCommand(command, false, value);
        syncVisual();
    };
    const toolbarButton = (label, title, command) => {
        const button = element('button', {className: 'editor-tool', text: label, attributes: {type: 'button', title, 'aria-label': title}});
        button.addEventListener('click', () => run(command));
        return button;
    };

    const format = element('select', {attributes: {'aria-label': 'Textformat', title: 'Textformat'}});
    [['p', 'Absatz'], ['h2', 'Überschrift 2'], ['h3', 'Überschrift 3'], ['blockquote', 'Zitat']].forEach(([value, label]) => {
        format.append(element('option', {text: label, attributes: {value}}));
    });
    format.addEventListener('change', () => run('formatBlock', format.value));

    const size = element('select', {attributes: {'aria-label': 'Textgröße', title: 'Textgröße'}});
    [['2', 'Klein'], ['3', 'Normal'], ['4', 'Groß'], ['5', 'Sehr groß']].forEach(([value, label]) => {
        size.append(element('option', {text: label, attributes: {value}}));
    });
    size.value = '3';
    size.addEventListener('change', () => run('fontSize', size.value));

    const color = element('input', {attributes: {type: 'color', value: '#174f35', title: 'Textfarbe', 'aria-label': 'Textfarbe'}});
    color.addEventListener('input', () => run('foreColor', color.value));

    const link = element('button', {className: 'editor-tool', text: 'Link', attributes: {type: 'button', title: 'Link einfügen'}});
    link.addEventListener('click', () => {
        const url = window.prompt('Zieladresse des Links:');
        if (url) run('createLink', url);
    });

    const table = element('button', {className: 'editor-tool', text: 'Tabelle', attributes: {type: 'button', title: 'Tabelle einfügen'}});
    table.addEventListener('click', () => {
        const selection = window.getSelection();
        const selectedRange = selection?.rangeCount && editor.contains(selection.anchorNode)
            ? selection.getRangeAt(0).cloneRange()
            : null;
        const dialog = element('dialog', {className: 'table-dialog'});
        const rows = element('input', {attributes: {type: 'number', min: '1', max: '20', value: '3', required: 'required'}});
        const columns = element('input', {attributes: {type: 'number', min: '1', max: '8', value: '2', required: 'required'}});
        const header = element('input', {attributes: {type: 'checkbox', checked: 'checked'}});
        header.checked = true;
        const stripedRows = element('input', {attributes: {type: 'checkbox'}});
        const form = element('form', {className: 'table-form', attributes: {method: 'dialog'}, children: [
            element('div', {children: [element('p', {className: 'eyebrow', text: 'Rich Text'}), element('h2', {text: 'Tabelle einfügen'})]}),
            element('div', {className: 'form-grid', children: [
                element('label', {className: 'field', children: [element('span', {text: 'Zeilen'}), rows]}),
                element('label', {className: 'field', children: [element('span', {text: 'Spalten'}), columns]}),
            ]}),
            element('label', {className: 'check-field', children: [header, element('span', {text: 'Erste Zeile als Kopfzeile'})]}),
            element('label', {className: 'check-field', children: [stripedRows, element('span', {text: 'Zeilen abwechselnd einfärben'})]}),
            element('div', {className: 'editor-actions', children: [
                element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}}),
                element('button', {className: 'button', text: 'Tabelle einfügen', attributes: {type: 'submit'}}),
            ]}),
        ]});
        form.querySelector('.secondary-button').addEventListener('click', () => dialog.close());
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const rowCount = Math.max(1, Math.min(20, Number.parseInt(rows.value, 10) || 1));
            const columnCount = Math.max(1, Math.min(8, Number.parseInt(columns.value, 10) || 1));
            const tableElement = document.createElement('table');
            if (stripedRows.checked) tableElement.classList.add('table-striped');
            const body = document.createElement('tbody');
            if (header.checked) {
                const head = document.createElement('thead');
                const row = document.createElement('tr');
                for (let columnIndex = 0; columnIndex < columnCount; columnIndex += 1) {
                    const cell = document.createElement('th');
                    cell.textContent = `Spalte ${columnIndex + 1}`;
                    row.append(cell);
                }
                head.append(row);
                tableElement.append(head);
            }
            const bodyRowCount = header.checked ? Math.max(1, rowCount - 1) : rowCount;
            for (let rowIndex = 0; rowIndex < bodyRowCount; rowIndex += 1) {
                const row = document.createElement('tr');
                for (let columnIndex = 0; columnIndex < columnCount; columnIndex += 1) {
                    const cell = document.createElement('td');
                    cell.textContent = 'Inhalt';
                    row.append(cell);
                }
                body.append(row);
            }
            tableElement.append(body);
            editor.focus();
            if (selectedRange) {
                const currentSelection = window.getSelection();
                currentSelection?.removeAllRanges();
                currentSelection?.addRange(selectedRange);
            }
            document.execCommand('insertHTML', false, `${tableElement.outerHTML}<p><br></p>`);
            syncVisual();
            dialog.close();
        });
        dialog.addEventListener('close', () => dialog.remove());
        dialog.append(form);
        document.body.append(dialog);
        dialog.showModal();
    });

    const toggle = element('button', {className: 'editor-tool html-toggle', text: 'HTML', attributes: {type: 'button', title: 'HTML-Quelltext bearbeiten'}});
    let htmlMode = false;
    toggle.addEventListener('click', () => {
        htmlMode = !htmlMode;
        if (htmlMode) {
            source.value = editor.innerHTML;
            editor.hidden = true;
            source.hidden = false;
            toggle.textContent = 'Visuell';
            source.focus();
        } else {
            editor.innerHTML = source.value;
            block.content = source.value;
            onChange?.(block.content);
            source.hidden = true;
            editor.hidden = false;
            toggle.textContent = 'HTML';
            editor.focus();
        }
    });
    editor.addEventListener('input', syncVisual);
    editor.addEventListener('paste', (event) => {
        event.preventDefault();
        const plainText = event.clipboardData?.getData('text/plain') || '';
        if (!document.execCommand('insertText', false, plainText)) {
            const selection = window.getSelection();
            if (selection?.rangeCount) {
                const range = selection.getRangeAt(0);
                range.deleteContents();
                const textNode = document.createTextNode(plainText);
                range.insertNode(textNode);
                range.setStartAfter(textNode);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        }
        syncVisual();
    });
    source.addEventListener('input', () => {
        block.content = source.value;
        onChange?.(block.content);
    });

    const toolbar = element('div', {className: 'rich-text-toolbar', attributes: {role: 'toolbar', 'aria-label': 'Text formatieren'}, children: [
        format,
        size,
        toolbarButton('B', 'Fett', 'bold'),
        toolbarButton('I', 'Kursiv', 'italic'),
        toolbarButton('U', 'Unterstrichen', 'underline'),
        toolbarButton('• Liste', 'Aufzählung', 'insertUnorderedList'),
        toolbarButton('1. Liste', 'Nummerierte Liste', 'insertOrderedList'),
        link,
        toolbarButton('Link lösen', 'Link entfernen', 'unlink'),
        table,
        color,
        toolbarButton('Format löschen', 'Formatierung entfernen', 'removeFormat'),
        toggle,
    ]});

    return element('div', {className: 'rich-text-editor', children: [toolbar, editor, source]});
};
const openImagePicker = async (onSelect) => {
    const dialog = element('dialog', {className: 'media-dialog'});
    const close = element('button', {className: 'text-button', text: 'Schließen', attributes: {type: 'button'}});
    close.addEventListener('click', () => dialog.close());
    const content = element('div', {className: 'media-grid', children: [element('p', {text: 'Bilder werden geladen …'})]});
    dialog.append(
        element('header', {className: 'media-dialog-header', children: [
            element('div', {children: [element('p', {className: 'eyebrow', text: 'Medien'}), element('h2', {text: 'Bild auswählen'})]}),
            close,
        ]}),
        content,
    );
    dialog.addEventListener('close', () => dialog.remove());
    document.body.append(dialog);
    dialog.showModal();

    try {
        const media = await request('/api/admin/v1/media/images');
        if (!media.items.length) {
            content.replaceChildren(emptyState('Es wurden noch keine Bilder hochgeladen.'));
            return;
        }
        content.replaceChildren(...media.items.map((image) => {
            const choose = element('button', {className: 'secondary-button full', text: 'Auswählen', attributes: {type: 'button'}});
            choose.addEventListener('click', () => {
                onSelect(image);
                dialog.close();
            });
            return element('article', {className: 'media-card', children: [
                element('img', {attributes: {src: image.url, alt: '', loading: 'lazy'}}),
                element('strong', {text: image.originalName}),
                element('small', {text: `${image.width} × ${image.height} px`}),
                ...(image.source ? [element('small', {className: 'media-card-source', text: `Quelle: ${image.source}`})] : []),
                choose,
            ]});
        }));
    } catch (error) {
        content.replaceChildren(emptyState(error.message));
    }
};
const collectionItemMediaEditor = (item, key) => {
    const media = field('Bild-URL (optional)', `collection-media-${key}`, item.mediaUrl || '');
    const alt = field('Alternativtext (optional; leer = dekorativ)', `collection-alt-${key}`, item.mediaAlt || '');
    const source = field('Bildquelle (optional)', `collection-source-${key}`, item.mediaSource || '');
    const mediaInput = media.querySelector('input');
    const altInput = alt.querySelector('input');
    const sourceInput = source.querySelector('input');
    sourceInput.maxLength = 300;
    let storedSource = item.mediaSource || null;
    mediaInput.addEventListener('input', () => item.mediaUrl = mediaInput.value || null);
    altInput.addEventListener('input', () => item.mediaAlt = altInput.value || null);
    sourceInput.addEventListener('input', () => item.mediaSource = sourceInput.value || null);
    sourceInput.addEventListener('blur', async () => {
        const normalizedSource = sourceInput.value.trim() || null;
        if (!item.mediaUrl?.startsWith('/uploads/media/') || normalizedSource === storedSource) return;
        try {
            const updated = await request('/api/admin/v1/media/images/source', {
                method: 'PATCH',
                body: JSON.stringify({url: item.mediaUrl, source: normalizedSource}),
            });
            storedSource = updated.source;
            item.mediaSource = updated.source;
            sourceInput.value = updated.source || '';
            toast('Die Bildquelle wurde in der Medienbibliothek aktualisiert.');
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    const uploadInput = element('input', {attributes: {type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', hidden: 'hidden'}});
    const uploadButton = element('button', {className: 'secondary-button', text: 'Bild hochladen', attributes: {type: 'button'}});
    const selectButton = element('button', {className: 'secondary-button', text: 'Bild auswählen', attributes: {type: 'button'}});
    const uploadMessage = element('small', {className: 'upload-message', attributes: {'aria-live': 'polite'}});
    uploadButton.addEventListener('click', () => uploadInput.click());
    selectButton.addEventListener('click', () => openImagePicker((image) => {
        item.mediaUrl = image.url;
        item.mediaSource = image.source || null;
        mediaInput.value = image.url;
        sourceInput.value = image.source || '';
        storedSource = image.source || null;
        uploadMessage.textContent = `${image.originalName} wurde ausgewählt.`;
        toast(`${image.originalName} wurde ausgewählt.`);
    }));
    uploadInput.addEventListener('change', async () => {
        const image = uploadInput.files?.[0];
        if (!image) return;
        const body = new FormData();
        body.append('image', image);
        body.append('source', sourceInput.value.trim());
        uploadButton.disabled = true;
        uploadMessage.textContent = 'Bild wird hochgeladen …';
        try {
            const stored = await request('/api/admin/v1/media/images', {method: 'POST', body});
            item.mediaUrl = stored.url;
            item.mediaSource = stored.source || null;
            mediaInput.value = stored.url;
            sourceInput.value = stored.source || '';
            storedSource = stored.source || null;
            uploadMessage.textContent = `${stored.originalName} wurde hochgeladen (${stored.width} × ${stored.height} px).`;
            toast(`${stored.originalName} wurde hochgeladen.`);
        } catch (error) {
            uploadMessage.textContent = error.message;
            toast(error.message, 'error');
        } finally {
            uploadButton.disabled = false;
            uploadInput.value = '';
        }
    });

    return element('div', {className: 'collection-media-editor', children: [
        element('div', {className: 'media-input-row', children: [media, selectButton, uploadButton, uploadInput]}),
        uploadMessage,
        alt,
        source,
        element('small', {text: 'Bei Bibliotheksbildern wird die gespeicherte Quelle automatisch übernommen.'}),
    ]});
};
const blockEditor = (block, index, handlers) => {
    const card = element('section', {className: 'block-editor'});
    const moveUp = element('button', {className: 'editor-tool', text: '↑', attributes: {type: 'button', title: 'Block nach oben', 'aria-label': 'Block nach oben'}});
    const moveDown = element('button', {className: 'editor-tool', text: '↓', attributes: {type: 'button', title: 'Block nach unten', 'aria-label': 'Block nach unten'}});
    moveUp.disabled = index === 0;
    moveDown.disabled = index === handlers.lastIndex;
    moveUp.addEventListener('click', () => handlers.onMove(index, index - 1));
    moveDown.addEventListener('click', () => handlers.onMove(index, index + 1));
    const dragLabel = element('div', {className: 'drag-label', attributes: {draggable: 'true', title: 'Block ziehen'}, children: [
        element('span', {text: '↕', attributes: {'aria-hidden': 'true'}}),
        element('strong', {text: BLOCK_TYPES[block.type] || block.type}),
    ]});
    dragLabel.addEventListener('dragstart', (event) => {
        card.classList.add('dragging');
        event.dataTransfer?.setData('text/plain', String(index));
        handlers.onDragStart(index);
    });
    dragLabel.addEventListener('dragend', () => {
        card.classList.remove('dragging');
        handlers.onDragEnd();
    });
    card.addEventListener('dragover', (event) => {
        event.preventDefault();
        card.classList.add('drag-over');
    });
    card.addEventListener('dragleave', () => card.classList.remove('drag-over'));
    card.addEventListener('drop', (event) => {
        event.preventDefault();
        card.classList.remove('drag-over');
        handlers.onDrop(index);
    });
    card.append(element('div', {className: 'block-editor-heading', children: [
        dragLabel,
        element('div', {className: 'block-move-actions', children: [moveUp, moveDown]}),
    ]}));
    if (block.type === 'feature_collection') {
        block.collectionColumns = Number.isInteger(block.collectionColumns) ? block.collectionColumns : 3;
        block.collectionItems = Array.isArray(block.collectionItems) ? block.collectionItems : [];
        const heading = field('Collection-Überschrift', `block-collection-heading-${index}`, block.content || '');
        const headingInput = heading.querySelector('input');
        headingInput.required = true;
        headingInput.addEventListener('input', () => block.content = headingInput.value);
        const columns = element('select', {
            attributes: {id: `block-collection-columns-${index}`},
            children: [1, 2, 3, 4].map((count) => element('option', {
                text: `${count} ${count === 1 ? 'Spalte' : 'Spalten'}`,
                attributes: {value: String(count)},
            })),
        });
        columns.value = String(block.collectionColumns);
        columns.addEventListener('change', () => block.collectionColumns = Number.parseInt(columns.value, 10));
        const itemList = element('div', {className: 'collection-item-editor-list'});
        const renderItems = () => {
            if (block.collectionItems.length === 0) {
                itemList.replaceChildren(emptyState('Noch keine Einträge vorhanden. Füge den ersten Eintrag hinzu.'));
                return;
            }
            itemList.replaceChildren(...block.collectionItems.map((item, itemIndex) => {
                const title = field('Überschrift', `collection-title-${index}-${itemIndex}`, item.title || '');
                const titleInput = title.querySelector('input');
                titleInput.required = true;
                titleInput.maxLength = 160;
                titleInput.addEventListener('input', () => item.title = titleInput.value);
                const moveUp = element('button', {className: 'tree-icon-button', text: '↑', attributes: {type: 'button', title: 'Eintrag nach oben', 'aria-label': 'Eintrag nach oben'}});
                const moveDown = element('button', {className: 'tree-icon-button', text: '↓', attributes: {type: 'button', title: 'Eintrag nach unten', 'aria-label': 'Eintrag nach unten'}});
                const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', title: 'Eintrag entfernen', 'aria-label': 'Eintrag entfernen'}});
                moveUp.disabled = itemIndex === 0;
                moveDown.disabled = itemIndex === block.collectionItems.length - 1;
                moveUp.addEventListener('click', () => {
                    [block.collectionItems[itemIndex - 1], block.collectionItems[itemIndex]] = [block.collectionItems[itemIndex], block.collectionItems[itemIndex - 1]];
                    renderItems();
                });
                moveDown.addEventListener('click', () => {
                    [block.collectionItems[itemIndex], block.collectionItems[itemIndex + 1]] = [block.collectionItems[itemIndex + 1], block.collectionItems[itemIndex]];
                    renderItems();
                });
                remove.addEventListener('click', async () => {
                    const itemLabel = item.title || `Eintrag ${itemIndex + 1}`;
                    const confirmed = await confirmAction(
                        `„${itemLabel}“ entfernen?`,
                        'Der Collection-Eintrag mit Bild und Text wird entfernt. Die Änderung wird mit dem nächsten Speichern dauerhaft.',
                        'Eintrag entfernen',
                    );
                    if (!confirmed) return;
                    block.collectionItems.splice(itemIndex, 1);
                    renderItems();
                    toast('Collection-Eintrag wurde entfernt.');
                });

                return element('section', {className: 'collection-item-editor', children: [
                    element('header', {className: 'collection-item-editor-heading', children: [
                        element('strong', {text: `Eintrag ${itemIndex + 1}`}),
                        element('div', {className: 'block-move-actions', children: [moveUp, moveDown, remove]}),
                    ]}),
                    title,
                    element('div', {className: 'field', children: [
                        element('span', {text: 'Text (optional)'}),
                        richTextEditor(item, `${index}-collection-${itemIndex}`, null, `Text für ${item.title || `Eintrag ${itemIndex + 1}`}`),
                    ]}),
                    collectionItemMediaEditor(item, `${index}-${itemIndex}`),
                ]});
            }));
        };
        const addItem = element('button', {className: 'secondary-button', text: '＋ Eintrag hinzufügen', attributes: {type: 'button'}});
        addItem.addEventListener('click', () => {
            block.collectionItems.push(createCollectionItem());
            renderItems();
            itemList.lastElementChild?.querySelector('input')?.focus();
        });
        renderItems();
        card.append(
            heading,
            element('label', {className: 'field collection-columns-field', children: [element('span', {text: 'Spalten im Desktop-Grid'}), columns]}),
            element('small', {text: 'Auf kleinen Bildschirmen werden die Karten automatisch untereinander dargestellt.'}),
            itemList,
            addItem,
        );
    } else if (block.type === 'event') {
        const title = field('Veranstaltungsüberschrift', 'block-event-title-' + index, block.eventTitle || '');
        const date = field('Veranstaltungsdatum', 'block-event-date-' + index, block.eventDate || '', 'date');
        const time = field('Uhrzeit', 'block-event-time-' + index, block.eventTime || '14:00', 'time');
        const titleInput = title.querySelector('input');
        const dateInput = date.querySelector('input');
        const timeInput = time.querySelector('input');
        titleInput.required = true;
        dateInput.required = true;
        timeInput.required = true;
        titleInput.addEventListener('input', (event) => block.eventTitle = event.target.value || null);
        dateInput.addEventListener('input', (event) => block.eventDate = event.target.value || null);
        timeInput.addEventListener('input', (event) => block.eventTime = event.target.value || null);
        card.append(
            title,
            element('div', {className: 'form-grid', children: [date, time]}),
            element('div', {className: 'field', children: [
                element('span', {text: 'Zusatzinformationen zur Veranstaltung (optional)'}),
                richTextEditor(block, index + '-event-details', null, 'Zusatzinformationen zur Veranstaltung'),
            ]}),
        );
        const helpEnabled = element('input', {attributes: {type: 'checkbox', id: 'block-event-help-' + index}});
        helpEnabled.checked = block.eventHelpEnabled === true;
        const helpLabel = field('Beschriftung des Buttons', 'block-event-help-label-' + index, block.eventHelpButtonLabel || 'Ich möchte helfen!');
        const helpLabelInput = helpLabel.querySelector('input');
        helpLabelInput.addEventListener('input', () => block.eventHelpButtonLabel = helpLabelInput.value || 'Ich möchte helfen!');
        card.append(
            element('label', {className: 'check-field event-help-option', children: [
                helpEnabled,
                element('span', {text: 'Im Frontend den Button „Ich möchte helfen!“ mit Anmeldeformular anzeigen'}),
            ]}),
        );
        const activityList = element('div', {className: 'event-activity-editor-list'});
        const renderAssignments = () => {
            block.eventActivities = Array.isArray(block.eventActivities) ? block.eventActivities : [];
            activityList.replaceChildren(...block.eventActivities.map((assignment, assignmentIndex) => {
                const select = element('select', {attributes: {'aria-label': 'Aktivität'}});
                (handlers.activities || []).forEach((activity) => {
                    if (!activity.active && activity.id !== assignment.activityId) return;
                    if (activity.id !== assignment.activityId && block.eventActivities.some((item) => item.activityId === activity.id)) return;
                    select.append(element('option', {text: `${activity.name}${activity.active ? '' : ' (inaktiv)'}`, attributes: {value: activity.id}}));
                });
                select.value = assignment.activityId;
                select.addEventListener('change', () => assignment.activityId = select.value);
                const count = element('input', {attributes: {
                    type: 'number', min: '1', max: '999', value: String(assignment.requiredHelpers || 1),
                    'aria-label': 'Benötigte Helfer',
                }});
                const decrease = element('button', {className: 'activity-count-button', text: '−', attributes: {type: 'button', 'aria-label': 'Helferzahl verringern'}});
                const increase = element('button', {className: 'activity-count-button', text: '+', attributes: {type: 'button', 'aria-label': 'Helferzahl erhöhen'}});
                const setCount = (value) => {
                    const normalized = Math.min(999, Math.max(1, value || 1));
                    count.value = String(normalized);
                    assignment.requiredHelpers = normalized;
                };
                count.addEventListener('input', () => setCount(Number.parseInt(count.value, 10)));
                count.addEventListener('blur', () => setCount(Number.parseInt(count.value, 10)));
                decrease.addEventListener('click', () => setCount(Number.parseInt(count.value, 10) - 1));
                increase.addEventListener('click', () => setCount(Number.parseInt(count.value, 10) + 1));
                const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', title: 'Zuordnung entfernen', 'aria-label': 'Zuordnung entfernen'}});
                remove.addEventListener('click', () => {
                    block.eventActivities.splice(assignmentIndex, 1);
                    renderAssignments();
                });
                return element('div', {className: 'event-activity-editor-row', children: [
                    select,
                    element('div', {className: 'activity-count-control', children: [decrease, count, increase]}),
                    remove,
                ]});
            }));
        };
        const addActivity = element('button', {className: 'secondary-button', text: '＋ Aktivität zuordnen', attributes: {type: 'button'}});
        addActivity.addEventListener('click', () => {
            const available = (handlers.activities || []).find((activity) => activity.active && !block.eventActivities.some((item) => item.activityId === activity.id));
            if (!available) {
                toast('Keine weitere aktive Aktivität verfügbar.', 'error');
                return;
            }
            block.eventActivities.push({activityId: available.id, requiredHelpers: 1});
            renderAssignments();
        });
        renderAssignments();
        const activityEditor = element('fieldset', {className: 'event-activity-editor', children: [
            element('legend', {text: 'Aktivitäten für die Helferanmeldung'}),
            element('small', {text: 'Die benötigte Helferzahl gilt nur für diese Veranstaltung.'}),
            element('div', {className: 'event-activity-editor-head', children: [
                element('strong', {text: 'Aktivität'}),
                element('strong', {text: 'Benötigt'}),
            ]}),
            activityList,
            addActivity,
        ]});
        const helpConfiguration = element('div', {className: 'event-help-configuration', children: [helpLabel, activityEditor]});
        helpConfiguration.hidden = !helpEnabled.checked;
        helpEnabled.addEventListener('change', () => {
            block.eventHelpEnabled = helpEnabled.checked;
            if (helpEnabled.checked && !block.eventIdentifier) block.eventIdentifier = crypto.randomUUID();
            helpConfiguration.hidden = !helpEnabled.checked;
        });
        card.append(helpConfiguration);

        block.eventCallToActions = Array.isArray(block.eventCallToActions) ? block.eventCallToActions : [];
        const actionList = element('div', {className: 'event-call-action-editor-list'});
        const renderActions = () => {
            actionList.replaceChildren(...block.eventCallToActions.map((action, actionIndex) => {
                const label = field('Button-Beschriftung', `block-event-action-label-${index}-${actionIndex}`, action.label || 'Mehr erfahren');
                const labelInput = label.querySelector('input');
                labelInput.maxLength = 80;
                labelInput.required = true;
                labelInput.addEventListener('input', () => action.label = labelInput.value);

                const targetType = element('select', {attributes: {'aria-label': 'Art des Linkziels'}, children: [
                    element('option', {text: 'URL verlinken', attributes: {value: 'url'}}),
                    element('option', {text: 'CMS-Seite verlinken', attributes: {value: 'page'}}),
                ]});
                targetType.value = action.pageId ? 'page' : 'url';
                const targetField = element('div', {className: 'event-call-action-target'});
                const renderTarget = () => {
                    if (targetType.value === 'page') {
                        const pageSelect = element('select', {attributes: {'aria-label': 'Verlinkte CMS-Seite'}});
                        pageSelect.append(element('option', {text: 'Seite auswählen …', attributes: {value: ''}}));
                        flattenPageTree(buildPageTree(handlers.pages || [])).forEach(({page: candidate, depth}) => {
                            if (candidate.id === handlers.currentPageId) return;
                            pageSelect.append(element('option', {
                                text: `${'— '.repeat(depth)}${candidate.title}${candidate.visible ? '' : ' (ausgeblendet)'}`,
                                attributes: {value: candidate.id},
                            }));
                        });
                        pageSelect.value = action.pageId || '';
                        pageSelect.required = true;
                        pageSelect.addEventListener('change', () => action.pageId = pageSelect.value || null);
                        targetField.replaceChildren(element('label', {className: 'field', children: [element('span', {text: 'Verlinkte Seite'}), pageSelect]}));
                        return;
                    }
                    const url = field('URL', `block-event-action-url-${index}-${actionIndex}`, action.url || '/');
                    const urlInput = url.querySelector('input');
                    urlInput.maxLength = 2048;
                    urlInput.required = true;
                    urlInput.addEventListener('input', () => action.url = urlInput.value);
                    targetField.replaceChildren(url);
                };
                targetType.addEventListener('change', () => {
                    if (targetType.value === 'page') {
                        action.url = null;
                    } else {
                        action.pageId = null;
                        action.url = '/';
                    }
                    renderTarget();
                });
                renderTarget();
                const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', title: 'Aktionsbutton entfernen', 'aria-label': 'Aktionsbutton entfernen'}});
                remove.addEventListener('click', async () => {
                    const confirmed = await confirmAction('Aktionsbutton entfernen?', 'Der zusätzliche Aktionsbutton wird aus dieser Veranstaltung entfernt.', 'Entfernen');
                    if (!confirmed) return;
                    block.eventCallToActions.splice(actionIndex, 1);
                    renderActions();
                });
                return element('div', {className: 'event-call-action-editor-row', children: [
                    label,
                    element('label', {className: 'field', children: [element('span', {text: 'Linkziel'}), targetType]}),
                    targetField,
                    remove,
                ]});
            }));
        };
        const addAction = element('button', {className: 'secondary-button', text: '＋ Aktionsbutton hinzufügen', attributes: {type: 'button'}});
        addAction.addEventListener('click', () => {
            block.eventCallToActions.push({label: 'Mehr erfahren', url: '/', pageId: null});
            renderActions();
            actionList.lastElementChild?.querySelector('input')?.focus();
        });
        renderActions();
        card.append(element('fieldset', {className: 'event-call-action-editor', children: [
            element('legend', {text: 'Weitere Aktionsbuttons'}),
            element('small', {text: 'Optional können weitere Buttons auf eine URL oder eine CMS-Seite verweisen.'}),
            actionList,
            addAction,
        ]}));
    } else if (block.type === 'page_teaser') {
        const select = element('select', {attributes: {id: 'block-page-teaser-' + index}});
        select.append(element('option', {text: 'Zielseite auswählen …', attributes: {value: ''}}));
        flattenPageTree(buildPageTree(handlers.pages || [])).forEach(({page: candidate, depth}) => {
            if (candidate.id === handlers.currentPageId) return;
            select.append(element('option', {
                text: `${'— '.repeat(depth)}${candidate.title}${candidate.visible ? '' : ' (ausgeblendet)'}`,
                attributes: {value: candidate.id},
            }));
        });
        select.value = block.embeddedPageId || '';
        select.addEventListener('change', () => block.embeddedPageId = select.value || null);
        const linkLabel = field('Beschriftung des Links', 'block-page-teaser-label-' + index, block.linkLabel || 'Mehr erfahren');
        block.linkLabel = linkLabel.querySelector('input').value;
        linkLabel.querySelector('input').addEventListener('input', (event) => block.linkLabel = event.target.value || 'Mehr erfahren');
        card.append(
            element('label', {className: 'field', children: [
                element('span', {text: 'Verlinkte Unterseite'}),
                select,
                element('small', {text: 'Titel und Link werden automatisch aus der ausgewählten Seite übernommen.'}),
            ]}),
            element('div', {className: 'field', children: [
                element('span', {text: 'Teasertext'}),
                richTextEditor(block, index + '-page-teaser', null, 'Teasertext'),
            ]}),
            linkLabel,
        );
    } else if (block.type === 'embedded_page') {
        const select = element('select', {attributes: {id: 'block-page-' + index}});
        select.append(element('option', {text: 'Seite auswählen …', attributes: {value: ''}}));
        flattenPageTree(buildPageTree(handlers.pages || [])).forEach(({page: candidate, depth}) => {
            if (candidate.id === handlers.currentPageId) return;
            select.append(element('option', {
                text: `${'— '.repeat(depth)}${candidate.title}${candidate.visible ? '' : ' (ausgeblendet)'}`,
                attributes: {value: candidate.id},
            }));
        });
        select.value = block.embeddedPageId || '';
        select.addEventListener('change', () => block.embeddedPageId = select.value || null);
        card.append(element('label', {className: 'field', children: [
            element('span', {text: 'Einzubettende Seite'}),
            select,
            element('small', {text: 'Im Frontend werden nur veröffentlichte und sichtbare Zielseiten ausgegeben.'}),
        ]}));
    } else if (block.type === 'event_reference') {
        const select = element('select', {attributes: {id: 'block-event-reference-' + index}});
        select.append(element('option', {text: 'Veranstaltung auswählen …', attributes: {value: ''}}));
        const selectedValue = block.embeddedPageId && block.eventIdentifier
            ? `${block.embeddedPageId}::${block.eventIdentifier}`
            : '';
        let selectionAvailable = selectedValue === '';

        flattenPageTree(buildPageTree(handlers.pages || [])).forEach(({page: candidate}) => {
            if (candidate.id === handlers.currentPageId) return;
            (candidate.blocks || []).filter((candidateBlock) => candidateBlock.type === 'event' && candidateBlock.eventIdentifier).forEach((event) => {
                const value = `${candidate.id}::${event.eventIdentifier}`;
                const date = event.eventDate
                    ? new Intl.DateTimeFormat('de-DE').format(new Date(`${event.eventDate}T00:00:00`))
                    : 'Ohne Datum';
                select.append(element('option', {
                    text: `${date} · ${event.eventTitle || 'Veranstaltung'} — ${candidate.title}${candidate.visible ? '' : ' (Seite ausgeblendet)'}`,
                    attributes: {value},
                }));
                if (value === selectedValue) selectionAvailable = true;
            });
        });
        if (!selectionAvailable) {
            select.append(element('option', {
                text: 'Ausgewählte Veranstaltung ist nicht mehr verfügbar',
                attributes: {value: selectedValue},
            }));
        }
        select.value = selectedValue;
        select.addEventListener('change', () => {
            const separator = select.value.indexOf('::');
            block.embeddedPageId = separator < 0 ? null : select.value.slice(0, separator);
            block.eventIdentifier = separator < 0 ? null : select.value.slice(separator + 2);
        });
        card.append(element('label', {className: 'field', children: [
            element('span', {text: 'Einzubettende Veranstaltung'}),
            select,
            element('small', {text: 'Datum, Uhrzeit, Bild, Text und Helferanmeldung werden aus der veröffentlichten Veranstaltung übernommen.'}),
        ]}));
    } else if (block.type === 'extension') {
        const select = element('select', {attributes: {id: 'block-extension-' + index}, children: [
            element('option', {text: 'Mitgliedsantrag', attributes: {value: 'membership_application'}}),
            element('option', {text: 'Veranstaltungen: aktuelles Jahr', attributes: {value: 'events_current_year'}}),
            element('option', {text: 'Arbeitseinsätze: aktuelles Jahr', attributes: {value: 'work_assignments_current_year'}}),
            element('option', {text: 'Veranstaltung: nächste', attributes: {value: 'next_event'}}),
            element('option', {text: 'Arbeitseinsatz: nächste', attributes: {value: 'next_work_assignment'}}),
            element('option', {text: 'Veranstaltung/Arbeitseinsatz: nächste', attributes: {value: 'next_event_or_work_assignment'}}),
            element('option', {text: 'Sauna: Belegungskalender und Anfrage', attributes: {value: 'sauna'}}),
        ]});
        select.value = block.extensionKey || 'membership_application';
        block.extensionKey = select.value;
        select.addEventListener('change', () => block.extensionKey = select.value);
        card.append(element('label', {className: 'field', children: [
            element('span', {text: 'Seitenerweiterung'}),
            select,
            element('small', {text: 'Rendert das Beitrittsformular im Frontend. Eingegangene Anträge erscheinen im Bereich „Mitgliedsanträge“.'}),
        ]}));
    } else if (block.type === 'image') {
        block.content = '';
    } else {
        const contentLabel = block.type === 'custom_html' ? 'HTML (wird sicher bereinigt)' : 'Inhalt';
        const usesRichText = block.type !== 'custom_html';
        const content = usesRichText
            ? richTextEditor(block, index)
            : field(contentLabel, 'block-content-' + index, block.content, 'textarea');
        if (!usesRichText) {
            content.querySelector('textarea').addEventListener('input', (event) => block.content = event.target.value);
        }
        card.append(content);
    }

    if (block.type === 'image' || block.type === 'image_text' || block.type === 'page_teaser' || block.type === 'event' || block.type === 'event_reference') {
        const optionalMedia = block.type === 'page_teaser' || block.type === 'event' || block.type === 'event_reference';
        const media = field(optionalMedia ? 'Bild-URL (optional)' : 'Bild-URL', 'block-media-' + index, block.mediaUrl || '');
        const alt = field('Alternativtext (optional; leer = dekorativ)', 'block-alt-' + index, block.mediaAlt || '');
        const source = field('Bildquelle (optional)', 'block-source-' + index, block.mediaSource || '');
        const mediaInput = media.querySelector('input');
        const sourceInput = source.querySelector('input');
        let storedSource = block.mediaSource || null;
        mediaInput.addEventListener('input', (event) => block.mediaUrl = event.target.value || null);
        alt.querySelector('input').addEventListener('input', (event) => block.mediaAlt = event.target.value || null);
        sourceInput.setAttribute('maxlength', '300');
        sourceInput.addEventListener('input', (event) => block.mediaSource = event.target.value || null);
        sourceInput.addEventListener('blur', async () => {
            const normalizedSource = sourceInput.value.trim() || null;
            if (!block.mediaUrl?.startsWith('/uploads/media/') || normalizedSource === storedSource) return;
            try {
                const updated = await request('/api/admin/v1/media/images/source', {
                    method: 'PATCH',
                    body: JSON.stringify({url: block.mediaUrl, source: normalizedSource}),
                });
                storedSource = updated.source;
                block.mediaSource = updated.source;
                sourceInput.value = updated.source || '';
                toast('Die Bildquelle wurde in der Medienbibliothek aktualisiert.');
            } catch (error) {
                toast(error.message, 'error');
            }
        });
        const uploadInput = element('input', {attributes: {type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', hidden: 'hidden'}});
        const uploadButton = element('button', {className: 'secondary-button', text: 'Bild hochladen', attributes: {type: 'button'}});
        const selectButton = element('button', {className: 'secondary-button', text: 'Bild auswählen', attributes: {type: 'button'}});
        const uploadMessage = element('small', {className: 'upload-message', attributes: {'aria-live': 'polite'}});
        uploadButton.addEventListener('click', () => uploadInput.click());
        selectButton.addEventListener('click', () => openImagePicker((image) => {
            block.mediaUrl = image.url;
            block.mediaSource = image.source || null;
            mediaInput.value = image.url;
            sourceInput.value = image.source || '';
            storedSource = image.source || null;
            uploadMessage.textContent = `${image.originalName} wurde ausgewählt.`;
            toast(`${image.originalName} wurde ausgewählt.`);
        }));
        uploadInput.addEventListener('change', async () => {
            const image = uploadInput.files?.[0];
            if (!image) return;
            const body = new FormData();
            body.append('image', image);
            body.append('source', sourceInput.value.trim());
            uploadButton.disabled = true;
            uploadMessage.textContent = 'Bild wird hochgeladen …';
            try {
                const stored = await request('/api/admin/v1/media/images', {method: 'POST', body});
                block.mediaUrl = stored.url;
                block.mediaSource = stored.source || null;
                mediaInput.value = stored.url;
                sourceInput.value = stored.source || '';
                storedSource = stored.source || null;
                uploadMessage.textContent = `${stored.originalName} wurde hochgeladen (${stored.width} × ${stored.height} px).`;
                toast(`${stored.originalName} wurde hochgeladen.`);
            } catch (error) {
                uploadMessage.textContent = error.message;
                toast(error.message, 'error');
            } finally {
                uploadButton.disabled = false;
                uploadInput.value = '';
            }
        });
        card.append(
            element('div', {className: 'media-input-row', children: [media, selectButton, uploadButton, uploadInput]}),
            uploadMessage,
            alt,
            source,
            element('small', {text: 'Bei Bibliotheksbildern wird diese Quelle gespeichert und bei jeder späteren Auswahl automatisch übernommen.'}),
            ...(block.type === 'event_reference' ? [element('small', {text: 'Ohne eigenes Bild wird das Bild der ausgewählten Veranstaltung verwendet.'})] : []),
        );
    }
    if (block.type === 'image_text' || block.type === 'page_teaser') {
        if (block.type === 'image_text') {
            const imageLink = field('Linkziel des Bildes (optional)', 'block-image-link-' + index, block.linkUrl || '', 'url');
            imageLink.querySelector('input').addEventListener('input', (event) => block.linkUrl = event.target.value || null);
            card.append(imageLink);
        }

        const layout = element('select', {attributes: {id: 'block-layout-' + index}});
        layout.append(
            element('option', {text: 'Bild links, Text rechts', attributes: {value: 'image_left'}}),
            element('option', {text: 'Text links, Bild rechts', attributes: {value: 'image_right'}}),
        );
        layout.value = block.layout || 'image_left';
        block.layout = layout.value;
        layout.addEventListener('change', () => block.layout = layout.value);
        card.append(element('label', {className: 'field', children: [element('span', {text: 'Anordnung'}), layout]}));

        const width = field('Bildbreite in Prozent', 'block-width-' + index, block.imageWidthPercent || 50, 'number');
        const widthInput = width.querySelector('input');
        widthInput.setAttribute('min', '20');
        widthInput.setAttribute('max', '80');
        widthInput.setAttribute('step', '5');
        block.imageWidthPercent = Number(widthInput.value);
        widthInput.addEventListener('input', () => block.imageWidthPercent = Number(widthInput.value));

        const optionField = (label, name, options, selected) => {
            const select = element('select', {attributes: {id: name}});
            options.forEach(([value, text]) => select.append(element('option', {text, attributes: {value}})));
            select.value = selected;
            return {field: element('label', {className: 'field', children: [element('span', {text: label}), select]}), select};
        };
        const vertical = optionField('Text vertikal', 'block-vertical-' + index, [
            ['top', 'Oben beginnen'], ['center', 'Vertikal zentriert'], ['bottom', 'Unten ausrichten'],
        ], block.verticalAlignment || 'center');
        const horizontal = optionField('Text horizontal', 'block-horizontal-' + index, [
            ['left', 'Linksbündig'], ['center', 'Zentriert'], ['right', 'Rechtsbündig'],
        ], block.textAlignment || 'left');
        const fit = optionField('Bilddarstellung', 'block-fit-' + index, [
            ['cover', 'Fläche ausfüllen / zuschneiden'], ['contain', 'Vollständig anzeigen'],
        ], block.imageFit || 'cover');
        block.verticalAlignment = vertical.select.value;
        block.textAlignment = horizontal.select.value;
        block.imageFit = fit.select.value;
        vertical.select.addEventListener('change', () => block.verticalAlignment = vertical.select.value);
        horizontal.select.addEventListener('change', () => block.textAlignment = horizontal.select.value);
        fit.select.addEventListener('change', () => block.imageFit = fit.select.value);
        card.append(element('div', {className: 'layout-options', children: [width, vertical.field, horizontal.field, fit.field]}));
    }
    if (block.type === 'event_reference') {
        const layout = element('select', {attributes: {id: 'block-event-reference-layout-' + index}, children: [
            element('option', {text: 'Bild links, Veranstaltung rechts', attributes: {value: 'image_left'}}),
            element('option', {text: 'Veranstaltung links, Bild rechts', attributes: {value: 'image_right'}}),
            element('option', {text: 'Bild oben und zentriert', attributes: {value: 'image_top'}}),
        ]});
        layout.value = ['image_left', 'image_right', 'image_top'].includes(block.layout) ? block.layout : 'image_left';
        block.layout = layout.value;
        card.append(element('label', {className: 'field', children: [element('span', {text: 'Anordnung'}), layout]}));

        const width = field('Bildbreite in Prozent', 'block-event-reference-width-' + index, block.imageWidthPercent || 50, 'number');
        const widthInput = width.querySelector('input');
        widthInput.setAttribute('min', '20');
        widthInput.setAttribute('max', layout.value === 'image_top' ? '100' : '80');
        widthInput.setAttribute('step', '5');
        block.imageWidthPercent = Number(widthInput.value);
        widthInput.addEventListener('input', () => block.imageWidthPercent = Number(widthInput.value));
        layout.addEventListener('change', () => {
            block.layout = layout.value;
            widthInput.max = layout.value === 'image_top' ? '100' : '80';
            if (layout.value !== 'image_top' && Number(widthInput.value) > 80) {
                widthInput.value = '80';
                block.imageWidthPercent = 80;
            }
        });

        const optionField = (label, name, options, selected) => {
            const select = element('select', {attributes: {id: name}});
            options.forEach(([value, text]) => select.append(element('option', {text, attributes: {value}})));
            select.value = selected;
            return {field: element('label', {className: 'field', children: [element('span', {text: label}), select]}), select};
        };
        const vertical = optionField('Inhalt vertikal', 'block-event-reference-vertical-' + index, [
            ['top', 'Oben beginnen'], ['center', 'Vertikal zentriert'], ['bottom', 'Unten ausrichten'],
        ], block.verticalAlignment || 'center');
        const horizontal = optionField('Text horizontal', 'block-event-reference-horizontal-' + index, [
            ['left', 'Linksbündig'], ['center', 'Zentriert'], ['right', 'Rechtsbündig'],
        ], block.textAlignment || 'left');
        const fit = optionField('Bilddarstellung', 'block-event-reference-fit-' + index, [
            ['cover', 'Fläche ausfüllen / zuschneiden'], ['contain', 'Vollständig anzeigen'],
        ], block.imageFit || 'cover');
        block.verticalAlignment = vertical.select.value;
        block.textAlignment = horizontal.select.value;
        block.imageFit = fit.select.value;
        vertical.select.addEventListener('change', () => block.verticalAlignment = vertical.select.value);
        horizontal.select.addEventListener('change', () => block.textAlignment = horizontal.select.value);
        fit.select.addEventListener('change', () => block.imageFit = fit.select.value);
        card.append(element('div', {className: 'layout-options', children: [width, vertical.field, horizontal.field, fit.field]}));
    }
    if (block.type === 'image') {
        const width = field('Bildbreite in Prozent', 'block-width-' + index, block.imageWidthPercent || 100, 'number');
        const widthInput = width.querySelector('input');
        widthInput.setAttribute('min', '20');
        widthInput.setAttribute('max', '100');
        widthInput.setAttribute('step', '5');
        block.imageWidthPercent = Number(widthInput.value);
        widthInput.addEventListener('input', () => block.imageWidthPercent = Number(widthInput.value));

        const alignment = element('select', {attributes: {id: 'block-image-alignment-' + index}, children: [
            element('option', {text: 'Linksbündig', attributes: {value: 'left'}}),
            element('option', {text: 'Zentriert', attributes: {value: 'center'}}),
            element('option', {text: 'Rechtsbündig', attributes: {value: 'right'}}),
        ]});
        alignment.value = ['left', 'center', 'right'].includes(block.layout) ? block.layout : 'center';
        block.layout = alignment.value;
        alignment.addEventListener('change', () => block.layout = alignment.value);
        card.append(element('div', {className: 'layout-options', children: [
            width,
            element('label', {className: 'field', children: [element('span', {text: 'Bildausrichtung'}), alignment]}),
        ]}));
    }
    if (block.type === 'call_to_action') {
        const link = field('Link', 'block-link-' + index, block.linkUrl || '');
        const label = field('Linktext', 'block-label-' + index, block.linkLabel || '');
        link.querySelector('input').addEventListener('input', (event) => block.linkUrl = event.target.value || null);
        label.querySelector('input').addEventListener('input', (event) => block.linkLabel = event.target.value || null);
        card.append(link, label);
    }

    const remove = element('button', {className: 'text-button danger', text: 'Block entfernen', attributes: {type: 'button'}});
    remove.addEventListener('click', async () => {
        const confirmed = await confirmAction(
            'Block entfernen?',
            `Der Block „${BLOCK_TYPES[block.type] || block.type}“ wird aus der Seite entfernt. Die Änderung wird mit dem nächsten Speichern dauerhaft.`,
            'Block entfernen',
        );
        if (!confirmed) return;
        handlers.onRemove();
        toast('Block wurde entfernt.');
    });
    card.append(remove);

    return card;
};

const pagePayload = (form, blocks, page) => {
    const data = new FormData(form);
    return {
        title: data.get('title'),
        slug: data.get('slug'),
        navigationLabel: data.get('navigationLabel'),
        parentId: page && !canManagePageStructure() ? page.parentId : data.get('parentId') || null,
        navigationPosition: page && !canManagePageStructure() ? page.navigationPosition : Number(data.get('navigationPosition')),
        visible: data.get('visible') === 'on',
        showInNavigation: data.get('showInNavigation') === 'on',
        seoTitle: data.get('seoTitle') || null,
        seoDescription: data.get('seoDescription') || null,
        pageId: page?.id || null,
        version: page?.version || 0,
        blocks,
    };
};

const openPagePreview = async (payload, availablePages, currentPageId) => {
    const page = await request('/api/admin/v1/pages/preview', {method: 'POST', body: JSON.stringify(payload)});
    const dialog = element('dialog', {className: 'preview-dialog'});
    const frame = element('div', {className: 'preview-frame desktop'});
    const pagesById = new Map(availablePages.map((availablePage) => [availablePage.id, availablePage]));
    const previewContext = {visited: new Set(currentPageId ? [currentPageId] : []), pagesById, showEmbedErrors: true, isPreview: true};
    const article = renderContentCard(page, previewContext);
    frame.append(
        element('div', {className: 'preview-site-header', children: [
            element('strong', {text: 'Waldbad Borkheide'}),
            element('span', {text: page.navigationLabel}),
        ]}),
        element('main', {className: 'page-shell', children: [
            element('section', {className: 'page-hero', children: [
                element('p', {className: 'eyebrow', text: 'Entwurfsvorschau'}),
                element('h1', {text: page.title}),
                ...(page.seoDescription ? [element('p', {className: 'lead', text: page.seoDescription})] : []),
            ]}),
            article,
        ]}),
    );

    const desktop = element('button', {className: 'editor-tool active', text: 'Desktop', attributes: {type: 'button'}});
    const mobile = element('button', {className: 'editor-tool', text: 'Mobil', attributes: {type: 'button'}});
    const setViewport = (mode) => {
        frame.className = 'preview-frame ' + mode;
        desktop.classList.toggle('active', mode === 'desktop');
        mobile.classList.toggle('active', mode === 'mobile');
    };
    desktop.addEventListener('click', () => setViewport('desktop'));
    mobile.addEventListener('click', () => setViewport('mobile'));
    const close = element('button', {className: 'secondary-button', text: 'Vorschau schließen', attributes: {type: 'button'}});
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(
        element('header', {className: 'preview-toolbar', children: [
            element('strong', {text: 'Seitenvorschau – nicht veröffentlicht'}),
            element('div', {className: 'preview-actions', children: [desktop, mobile, close]}),
        ]}),
        element('div', {className: 'preview-stage', children: [frame]}),
    );
    document.body.append(dialog);
    dialog.showModal();
};

const pageEditor = (page, onSaved, pages = [], initialParentId = null, activities = []) => {
    const blocks = (page?.blocks || []).map((block) => ({...block}));
    const blockList = element('div', {className: 'block-list'});
    let draggedIndex = null;

    const moveBlockToPosition = (from, position) => {
        if (from < 0 || from >= blocks.length || position < 0 || position > blocks.length) return;
        const [block] = blocks.splice(from, 1);
        const adjustedPosition = from < position ? position - 1 : position;
        blocks.splice(adjustedPosition, 0, block);
        draggedIndex = null;
        refreshBlocks();
    };

    const swapBlocks = (from, to) => {
        if (to < 0 || to >= blocks.length) return;
        [blocks[from], blocks[to]] = [blocks[to], blocks[from]];
        refreshBlocks();
    };

    const moveBlockToIndex = (from, to) => {
        if (from < 0 || from >= blocks.length || to < 0 || to >= blocks.length || from === to) return;
        const [block] = blocks.splice(from, 1);
        blocks.splice(to, 0, block);
        draggedIndex = null;
        refreshBlocks();
    };

    const blockInserter = (position) => {
        const inserter = element('div', {className: 'block-inserter'});
        const plus = element('button', {className: 'block-plus', text: '+', attributes: {type: 'button', title: 'Inhalt an dieser Stelle einfügen', 'aria-label': 'Inhalt an dieser Stelle einfügen'}});
        const panel = element('div', {className: 'block-insert-panel'});
        panel.hidden = true;
        const select = element('select', {attributes: {'aria-label': 'Neuer Blocktyp'}});
        Object.entries(BLOCK_TYPES).forEach(([type, label]) => select.append(element('option', {text: label, attributes: {value: type}})));
        const insert = element('button', {className: 'secondary-button', text: 'Einfügen', attributes: {type: 'button'}});
        insert.addEventListener('click', () => {
            blocks.splice(position, 0, createBlock(select.value));
            refreshBlocks();
        });
        plus.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            plus.setAttribute('aria-expanded', String(!panel.hidden));
            if (!panel.hidden) select.focus();
        });
        inserter.addEventListener('dragover', (event) => {
            event.preventDefault();
            inserter.classList.add('drag-over');
        });
        inserter.addEventListener('dragleave', () => inserter.classList.remove('drag-over'));
        inserter.addEventListener('drop', (event) => {
            event.preventDefault();
            inserter.classList.remove('drag-over');
            if (draggedIndex !== null) moveBlockToPosition(draggedIndex, position);
        });
        panel.append(select, insert);
        inserter.append(plus, panel);
        return inserter;
    };

    const refreshBlocks = () => {
        const children = [];
        blocks.forEach((block, index) => {
            children.push(blockInserter(index));
            children.push(blockEditor(block, index, {
                lastIndex: blocks.length - 1,
                pages,
                activities,
                currentPageId: page?.id || null,
                onRemove: () => {
                    blocks.splice(index, 1);
                    refreshBlocks();
                },
                onMove: swapBlocks,
                onDragStart: (dragIndex) => draggedIndex = dragIndex,
                onDragEnd: () => {
                    draggedIndex = null;
                    blockList.querySelectorAll('.drag-over').forEach((node) => node.classList.remove('drag-over'));
                },
                onDrop: (position) => {
                    if (draggedIndex !== null) moveBlockToIndex(draggedIndex, position);
                },
            }));
        });
        children.push(blockInserter(blocks.length));
        blockList.replaceChildren(...children);
    };

    const message = element('p', {className: 'form-message', attributes: {'aria-live': 'polite'}});
    const runStatusAction = async (action, saveFirst = false) => {
        try {
            if (saveFirst) {
                await request('/api/admin/v1/pages/' + page.id, {
                    method: 'PUT',
                    body: JSON.stringify(pagePayload(form, blocks, page)),
                });
            }
            await request(`/api/admin/v1/pages/${page.id}/${action}`, {method: 'POST'});
            const messages = {
                'request-review': 'Seite wurde gespeichert und zur Prüfung eingereicht.',
                publish: 'Seite wurde gespeichert und veröffentlicht.',
                unpublish: 'Seite wurde zurückgezogen.',
            };
            toast(messages[action] || 'Status wurde aktualisiert.');
            await onSaved();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
    };
    const statusActions = element('div', {className: 'status-actions'});
    if (page && canEditPages(page.id) && page.status === 'draft') {
        const review = element('button', {className: 'secondary-button', text: 'Zur Prüfung', attributes: {type: 'button'}});
        review.addEventListener('click', () => runStatusAction('request-review', true));
        statusActions.append(review);
    }
    if (canPublishPages(page?.id || null) && page?.status !== 'archived') {
        const publish = element('button', {className: 'button', text: 'Veröffentlichen', attributes: {type: 'button'}});
        publish.addEventListener('click', () => page ? runStatusAction('publish', true) : savePage(true));
        statusActions.append(publish);
    }
    if (page && canPublishPages(page.id) && page.publishedAt && page.status !== 'archived') {
        const unpublish = element('button', {className: 'secondary-button', text: 'Zurückziehen', attributes: {type: 'button'}});
        unpublish.addEventListener('click', () => runStatusAction('unpublish'));
        statusActions.append(unpublish);
    }

    const previewButton = element('button', {className: 'secondary-button', text: 'Vorschau', attributes: {type: 'button'}});
    previewButton.addEventListener('click', async () => {
        try {
            await openPagePreview(pagePayload(form, blocks, page), pages, page?.id || null);
            message.textContent = '';
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
    });
    const saveButton = element('button', {className: 'button', text: 'Entwurf speichern', attributes: {type: 'submit'}});
    if (!canEditPages(page?.id || null) || page?.status === 'archived') saveButton.disabled = true;
    const form = element('form', {
        className: 'editor-form',
        children: [
            element('div', {className: 'editor-heading', children: [
                element('div', {children: [
                    element('p', {
                        className: 'eyebrow',
                        text: page
                            ? `Status: ${page.status}${page.publishedAt && page.status !== 'published' ? ' · letzte Version online' : ''}`
                            : 'Neue Seite',
                    }),
                    element('h2', {text: page?.title || 'Seite anlegen'}),
                ]}),
                element('div', {className: 'editor-actions', children: [statusActions, previewButton, saveButton]}),
            ]}),
            element('div', {className: 'form-grid', children: [
                field('Titel', 'title', page?.title),
                field('Slug', 'slug', page?.slug),
                field('Navigation', 'navigationLabel', page?.navigationLabel),
                field('Position', 'navigationPosition', page?.navigationPosition || 0, 'number'),
                parentPageField(pages, page, initialParentId),
                field('SEO-Titel', 'seoTitle', page?.seoTitle),
                field('SEO-Beschreibung', 'seoDescription', page?.seoDescription, 'textarea'),
            ]}),
            element('label', {className: 'check-field', children: [
                element('input', {attributes: {name: 'visible', type: 'checkbox'}}),
                element('span', {text: 'Im Frontend sichtbar'}),
            ]}),
            element('label', {className: 'check-field', children: [
                element('input', {attributes: {name: 'showInNavigation', type: 'checkbox'}}),
                element('span', {text: 'In Navigation anzeigen'}),
            ]}),
            element('p', {className: 'block-help', text: 'Mit + fügst du Inhalte an der gewünschten Stelle ein. Blöcke lassen sich ziehen oder mit den Pfeilen verschieben.'}),
            blockList,
            message,
        ],
    });
    const titleInput = form.querySelector('[name="title"]');
    const slugInput = form.querySelector('[name="slug"]');
    const parentInput = form.querySelector('[name="parentId"]');
    const navigationLabelInput = form.querySelector('[name="navigationLabel"]');
    const seoTitleInput = form.querySelector('[name="seoTitle"]');
    const initialAutomaticSlug = hierarchicalSlug(
        page?.title || '',
        page?.parentId || initialParentId || '',
        pages,
    );
    let updateSlugAutomatically = !page || page.slug === initialAutomaticSlug;
    let updateNavigationAutomatically = !page || page.navigationLabel === page.title;
    let updateSeoTitleAutomatically = !page || !page.seoTitle || page.seoTitle === page.title;
    const refreshAutomaticSlug = () => {
        if (updateSlugAutomatically) {
            slugInput.value = hierarchicalSlug(titleInput.value, parentInput.value, pages);
        }
    };
    if (!page) slugInput.readOnly = true;
    slugInput.addEventListener('input', () => updateSlugAutomatically = false);
    parentInput.addEventListener('change', refreshAutomaticSlug);
    navigationLabelInput.addEventListener('input', () => updateNavigationAutomatically = false);
    seoTitleInput.addEventListener('input', () => updateSeoTitleAutomatically = false);
    titleInput.addEventListener('input', () => {
        refreshAutomaticSlug();
        if (updateNavigationAutomatically) navigationLabelInput.value = titleInput.value.trim();
        if (updateSeoTitleAutomatically) seoTitleInput.value = titleInput.value.trim();
    });
    form.querySelector('[name="visible"]').checked = page?.visible ?? true;
    form.querySelector('[name="showInNavigation"]').checked = page?.showInNavigation ?? true;
    const savePage = async (publishAfterSave = false) => {
        const payload = pagePayload(form, blocks, page);
        try {
            const savedPage = await request(page ? '/api/admin/v1/pages/' + page.id : '/api/admin/v1/pages', {
                method: page ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });
            if (publishAfterSave) {
                await request(`/api/admin/v1/pages/${savedPage.id}/publish`, {method: 'POST'});
                message.textContent = 'Seite veröffentlicht.';
                toast('Seite wurde gespeichert und veröffentlicht.');
            } else {
                message.textContent = 'Entwurf gespeichert.';
                toast(page ? 'Entwurf wurde gespeichert.' : 'Seite wurde angelegt.');
            }
            await onSaved();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
    };
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await savePage();
    });
    refreshBlocks();

    return form;
};

const showPages = async () => {
    const [pages, activityData] = await Promise.all([
        request('/api/admin/v1/pages'),
        request('/api/admin/v1/event-activities'),
    ]);
    const activities = activityData.items || [];
    const list = element('div', {className: 'management-list page-tree-panel'});
    const pageById = new Map(pages.items.map((page) => [page.id, page]));
    let draggedPageId = null;
    let disabledPageIds = new Set();
    let dropTarget = null;
    const isDescendantOf = (pageId, possibleAncestorId) => {
        let current = pageById.get(pageId);
        while (current?.parentId) {
            if (current.parentId === possibleAncestorId) return true;
            current = pageById.get(current.parentId);
        }

        return false;
    };
    const siblingsOf = (parentId) => pages.items
        .filter((candidate) => candidate.parentId === parentId)
        .sort((left, right) => (left.navigationPosition - right.navigationPosition)
            || left.title.localeCompare(right.title, 'de') || left.id.localeCompare(right.id));
    const clearDropIndicator = () => {
        list.querySelectorAll('.drop-before, .drop-after, .drop-into')
            .forEach((node) => node.classList.remove('drop-before', 'drop-after', 'drop-into'));
        dropTarget = null;
    };
    const clearDragState = () => {
        draggedPageId = null;
        disabledPageIds = new Set();
        list.classList.remove('is-page-dragging');
        list.querySelectorAll('.dragging').forEach((node) => node.classList.remove('dragging'));
        clearDropIndicator();
    };
    // Ermittelt beim Ziehen anhand der Mausposition, über welcher Zeile der Zeiger steht und in
    // welchem Drittel: oberes/unteres Drittel = als Geschwisterseite davor/danach einsortieren,
    // mittleres Drittel = als Unterseite in die Zielseite hinein ablegen. So verhält sich das
    // Verschieben wie in einem Datei-Explorer, ohne eigene, dauerhaft sichtbare Ablagefelder.
    const updateDropTarget = (clientX, clientY) => {
        const rows = Array.from(list.querySelectorAll('.page-tree-row'));
        if (!rows.length) {
            clearDropIndicator();
            return;
        }
        let targetRow = rows.find((row) => {
            const rect = row.getBoundingClientRect();
            return clientY >= rect.top && clientY <= rect.bottom;
        });
        if (!targetRow) {
            const firstRect = rows[0].getBoundingClientRect();
            const lastRect = rows[rows.length - 1].getBoundingClientRect();
            if (clientY < firstRect.top) targetRow = rows[0];
            else if (clientY > lastRect.bottom) targetRow = rows[rows.length - 1];
        }
        const pageId = targetRow?.closest('.page-tree-node')?.dataset.pageId;
        const targetPage = pageId ? pageById.get(pageId) : null;
        if (!targetPage || pageId === draggedPageId || disabledPageIds.has(pageId)) {
            clearDropIndicator();
            return;
        }
        const rect = targetRow.getBoundingClientRect();
        const relativeY = (clientY - rect.top) / rect.height;
        let mode;
        let parentId;
        let position;
        if (relativeY < 0.3) {
            mode = 'drop-before';
            parentId = targetPage.parentId;
            position = siblingsOf(parentId).findIndex((candidate) => candidate.id === targetPage.id);
        } else if (relativeY > 0.7) {
            mode = 'drop-after';
            parentId = targetPage.parentId;
            position = siblingsOf(parentId).findIndex((candidate) => candidate.id === targetPage.id) + 1;
        } else {
            mode = 'drop-into';
            parentId = targetPage.id;
            position = siblingsOf(targetPage.id).length;
        }
        if (dropTarget?.rowEl === targetRow && dropTarget.mode === mode) return;
        clearDropIndicator();
        dropTarget = {rowEl: targetRow, parentId, position, mode};
        targetRow.classList.add(mode);
    };
    const applyDrop = async () => {
        const draggedPage = draggedPageId ? pageById.get(draggedPageId) : null;
        const target = dropTarget;
        clearDragState();
        if (!draggedPage || !target) return;
        const targetSiblings = siblingsOf(target.parentId);
        const sourceIndex = targetSiblings.findIndex((candidate) => candidate.id === draggedPage.id);
        const adjustedPosition = sourceIndex >= 0 && sourceIndex < target.position ? target.position - 1 : target.position;
        if (draggedPage.parentId === target.parentId && sourceIndex === adjustedPosition) return;
        try {
            await request(`/api/admin/v1/pages/${draggedPage.id}/position`, {
                method: 'PUT',
                body: JSON.stringify({
                    parentId: target.parentId,
                    navigationPosition: adjustedPosition,
                    version: draggedPage.version,
                }),
            });
            const label = target.parentId ? `unter „${pageById.get(target.parentId)?.title || 'Seite'}“` : 'als Hauptseite';
            toast(`„${draggedPage.title}“ wurde ${label} einsortiert.`);
            await showPages();
        } catch (error) {
            toast(error.message, 'error');
            await showPages();
        }
    };
    if (canManagePageStructure()) {
        const create = element('button', {className: 'secondary-button full', text: '＋ Neue Hauptseite', attributes: {type: 'button'}});
        create.addEventListener('click', () => workspace.replaceChildren(pageEditor(null, showPages, pages.items, null, activities)));
        list.append(create);
    }
    const renderTreeNode = (page, siblingIndex, siblings) => {
        const title = element('button', {
            className: 'page-tree-title',
            attributes: {type: 'button', title: `${page.title} bearbeiten`},
            children: [
                element('span', {className: 'page-icon', text: page.visible ? '▤' : '⊘', attributes: {'aria-hidden': 'true'}}),
                element('span', {children: [
                    element('strong', {text: page.title}),
                    element('small', {
                        text: `${page.status}${page.publishedAt && page.status !== 'published' ? ' · letzte Version online' : ''}${page.visible ? '' : ' · ausgeblendet'} · /${page.slug}`,
                    }),
                ]}),
            ],
        });
        title.addEventListener('click', () => workspace.replaceChildren(pageEditor(page, showPages, pages.items, null, activities)));

        const actionDefinitions = [];
        if (canManagePageStructure()) {
            const runPageAction = async (button, url, method, success) => {
                button.disabled = true;
                try {
                    await request(url, {method});
                    toast(success);
                    await showPages();
                } catch (error) {
                    toast(error.message, 'error');
                    button.disabled = false;
                }
            };
            actionDefinitions.push(
                {
                    icon: '＋',
                    label: `Unterseite zu ${page.title} hinzufügen`,
                    menuLabel: 'Unterseite hinzufügen',
                    run: () => workspace.replaceChildren(pageEditor(null, showPages, pages.items, page.id, activities)),
                },
                {
                    icon: '✎',
                    label: `${page.title} bearbeiten`,
                    menuLabel: 'Bearbeiten',
                    run: () => workspace.replaceChildren(pageEditor(page, showPages, pages.items, null, activities)),
                },
                {
                    icon: '⧉',
                    label: `${page.title} duplizieren`,
                    menuLabel: 'Duplizieren',
                    run: (button) => runPageAction(
                        button,
                        `/api/admin/v1/pages/${page.id}/duplicate`,
                        'POST',
                        `„${page.title}“ wurde als ausgeblendeter Entwurf dupliziert.`,
                    ),
                },
                {
                    icon: '↑',
                    label: `${page.title} nach oben verschieben`,
                    menuLabel: 'Nach oben verschieben',
                    disabled: siblingIndex === 0,
                    run: (button) => runPageAction(
                        button,
                        `/api/admin/v1/pages/${page.id}/move/up`,
                        'POST',
                        `„${page.title}“ wurde nach oben verschoben.`,
                    ),
                },
                {
                    icon: '↓',
                    label: `${page.title} nach unten verschieben`,
                    menuLabel: 'Nach unten verschieben',
                    disabled: siblingIndex === siblings.length - 1,
                    run: (button) => runPageAction(
                        button,
                        `/api/admin/v1/pages/${page.id}/move/down`,
                        'POST',
                        `„${page.title}“ wurde nach unten verschoben.`,
                    ),
                },
                {
                    icon: '✕',
                    label: `${page.title} löschen`,
                    menuLabel: 'Löschen',
                    danger: true,
                    run: async (button) => {
                        const confirmed = await confirmAction(
                            `„${page.title}“ löschen?`,
                            'Die Seite und ihre Inhalte werden dauerhaft gelöscht. Seiten mit Unterseiten oder Einbettungen können erst gelöscht werden, nachdem diese Abhängigkeiten entfernt wurden.',
                            'Seite löschen',
                        );
                        if (!confirmed) return;
                        await runPageAction(button, `/api/admin/v1/pages/${page.id}`, 'DELETE', `„${page.title}“ wurde gelöscht.`);
                    },
                },
            );
        }

        const createActionButton = (definition, mobile = false) => {
            const button = element('button', {
                className: mobile
                    ? `page-tree-menu-action${definition.danger ? ' danger' : ''}`
                    : `tree-icon-button${definition.danger ? ' danger' : ''}`,
                text: mobile ? definition.menuLabel : definition.icon,
                attributes: {type: 'button', title: definition.label, 'aria-label': definition.label},
            });
            button.disabled = definition.disabled === true;
            button.addEventListener('click', () => definition.run(button));

            return button;
        };
        const actionContainers = [];
        if (actionDefinitions.length) {
            const mobileActionMenu = element('details', {
                className: 'page-tree-action-menu',
                children: [
                    element('summary', {
                        className: 'page-tree-action-menu-toggle',
                        text: '⋮',
                        attributes: {title: `Aktionen für ${page.title}`, 'aria-label': `Aktionen für ${page.title}`},
                    }),
                    element('div', {
                        className: 'page-tree-action-menu-popover',
                        children: actionDefinitions.map((definition) => createActionButton(definition, true)),
                    }),
                ],
            });
            mobileActionMenu.querySelectorAll('.page-tree-menu-action').forEach((button) => {
                button.addEventListener('click', () => mobileActionMenu.removeAttribute('open'));
            });
            mobileActionMenu.addEventListener('toggle', () => {
                if (!mobileActionMenu.open) return;
                list.querySelectorAll('.page-tree-action-menu[open]').forEach((menu) => {
                    if (menu !== mobileActionMenu) menu.removeAttribute('open');
                });
            });
            actionContainers.push(
                element('div', {
                    className: 'page-tree-actions',
                    children: actionDefinitions.map((definition) => createActionButton(definition)),
                }),
                mobileActionMenu,
            );
        }

        const dragHandle = element('div', {
            className: 'page-tree-drag-handle',
            text: '↕',
            attributes: {title: `${page.title} ziehen`, 'aria-label': `${page.title} per Drag-and-drop verschieben`},
        });
        let pointerId = null;
        const endPointerDrag = async (event, drop) => {
            if (event.pointerId !== pointerId) return;
            dragHandle.releasePointerCapture?.(event.pointerId);
            pointerId = null;
            if (drop && dropTarget) await applyDrop();
            else clearDragState();
        };
        dragHandle.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) return;
            event.preventDefault();
            pointerId = event.pointerId;
            draggedPageId = page.id;
            disabledPageIds = new Set([
                page.id,
                ...pages.items.filter((candidate) => isDescendantOf(candidate.id, page.id)).map((candidate) => candidate.id),
            ]);
            list.classList.add('is-page-dragging');
            item.classList.add('dragging');
            dragHandle.setPointerCapture?.(event.pointerId);
        });
        dragHandle.addEventListener('pointermove', (event) => {
            if (event.pointerId !== pointerId) return;
            event.preventDefault();
            updateDropTarget(event.clientX, event.clientY);
        });
        dragHandle.addEventListener('pointerup', (event) => endPointerDrag(event, true));
        dragHandle.addEventListener('pointercancel', (event) => endPointerDrag(event, false));
        const row = element('div', {
            className: 'page-tree-row',
            children: [...(canManagePageStructure() ? [dragHandle] : []), title, ...actionContainers],
        });
        const item = element('li', {
            className: `page-tree-node${page.visible ? '' : ' is-hidden'}`,
            attributes: {'data-page-id': page.id},
            children: [row],
        });
        if (page.children.length) {
            const children = renderTreeLevel(page.children);
            const toggle = element('button', {className: 'tree-toggle', text: '▾', attributes: {type: 'button', title: 'Unterseiten ein- oder ausblenden', 'aria-label': `Unterseiten von ${page.title} ausblenden`, 'aria-expanded': 'true'}});
            toggle.addEventListener('click', () => {
                children.hidden = !children.hidden;
                toggle.textContent = children.hidden ? '▸' : '▾';
                toggle.setAttribute('aria-expanded', String(!children.hidden));
                toggle.setAttribute('aria-label', `Unterseiten von ${page.title} ${children.hidden ? 'anzeigen' : 'ausblenden'}`);
            });
            row.prepend(toggle);
            item.append(children);
        } else {
            row.prepend(element('span', {className: 'tree-toggle-placeholder', attributes: {'aria-hidden': 'true'}}));
        }

        return item;
    };
    const renderTreeLevel = (nodes, rootLevel = false) => element('ul', {
        className: rootLevel ? 'page-tree' : 'page-tree-children',
        children: nodes.map((node, index) => renderTreeNode(node, index, nodes)),
    });
    const tree = buildPageTree(pages.items);
    list.append(tree.length
        ? renderTreeLevel(tree, true)
        : emptyState('Noch keine Seiten vorhanden.'));
    workspace.replaceChildren(sectionHeading(
        'Seitenstruktur',
        'Seiten am ↕-Griff greifen und verschieben: am oberen oder unteren Rand einer Seite loslassen, um davor oder danach einzusortieren, in der Mitte, um als Unterseite abzulegen.',
    ), list);
};


export {showPages, richTextEditor, collectionItemMediaEditor};
