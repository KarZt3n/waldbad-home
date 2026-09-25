// Modul „Fotos“: Einträge des Fotoarchivs (Titel, Datum, Sichtbarkeit, Aktionsbuttons wie „Öffnen“
// oder „Ergebnisse“), nach Jahren gruppiert — öffentlich über die Seitenerweiterung `photo_albums`.

import {confirmAction, element, field, formatDateDE, formMessage, request, toast} from '../core.js';
import {emptyState, sectionHeading} from './ui.js';
import {canEditModule} from './session.js';
import {buildCallToActionEditor} from './events.js';
import {workspace} from './shell.js';

const openPhotoAlbumDialog = (album, pages, onSaved) => {
    const dialog = element('dialog', {className: 'event-schedule-dialog photo-album-dialog'});
    const key = album?.id || 'new';
    const title = field('Titel', `photo-album-title-${key}`, album?.title || '');
    const titleInput = title.querySelector('input');
    titleInput.required = true;
    titleInput.maxLength = 250;
    const date = field('Datum', `photo-album-date-${key}`, album?.date || '', 'date');
    const dateInput = date.querySelector('input');
    dateInput.required = true;
    const visible = element('input', {attributes: {type: 'checkbox'}});
    visible.checked = album?.visible !== false;
    const actions = (album?.actions || [{label: 'Öffnen', url: '', pageId: null}]).map((action) => ({...action}));
    const message = formMessage();
    const submit = element('button', {className: 'button', text: album ? 'Änderungen speichern' : 'Eintrag anlegen', attributes: {type: 'submit'}});
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
    const dialogActions = [cancel, submit];
    if (album) {
        const deleteButton = element('button', {className: 'button danger-button', text: 'Löschen', attributes: {type: 'button'}});
        deleteButton.addEventListener('click', async () => {
            if (!(await confirmAction(`„${album.title}“ löschen?`, 'Der Eintrag wird aus dem Fotoarchiv entfernt.', 'Löschen'))) return;
            try {
                await request(`/api/admin/v1/photo-albums/${album.id}`, {method: 'DELETE'});
                toast('Eintrag wurde gelöscht.');
                dialog.close();
                await onSaved();
            } catch (error) {
                toast(error.message, 'error');
            }
        });
        dialogActions.unshift(deleteButton);
    }
    const form = element('form', {className: 'event-schedule-dialog-content', children: [
        element('header', {children: [
            element('p', {className: 'eyebrow', text: album ? 'Foto-Eintrag bearbeiten' : 'Neuer Foto-Eintrag'}),
            element('h2', {text: album?.title || 'Foto-Eintrag anlegen'}),
        ]}),
        title,
        element('small', {text: 'Der Titel erscheint im Frontend genau so, z. B. „Flohmarkt am 26.04.2026“.'}),
        date,
        element('small', {text: 'Bestimmt das Jahr, unter dem der Eintrag erscheint, und die Reihenfolge (neueste zuerst).'}),
        element('label', {className: 'check-field', children: [visible, element('span', {text: 'Im Frontend sichtbar'})]}),
        buildCallToActionEditor(actions, pages, `photo-${key}`, {
            legend: 'Aktionsbuttons',
            hint: 'Z. B. „Öffnen“ für das Fotoalbum und „Ergebnisse“ für eine Ergebnisliste. Ohne Button erscheint der Eintrag als reiner Hinweis.',
            defaultLabel: 'Öffnen',
        }),
        message,
        element('div', {className: 'confirm-dialog-actions', children: dialogActions}),
    ]});
    cancel.addEventListener('click', () => dialog.close());
    close.addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        try {
            await request(album ? `/api/admin/v1/photo-albums/${album.id}` : '/api/admin/v1/photo-albums', {
                method: album ? 'PUT' : 'POST',
                body: JSON.stringify({
                    title: titleInput.value,
                    date: dateInput.value,
                    visible: visible.checked,
                    actions: actions.map((action) => ({label: action.label, url: action.pageId ? null : action.url, pageId: action.pageId || null})),
                }),
            });
            toast(album ? 'Eintrag wurde gespeichert.' : 'Eintrag wurde angelegt.');
            dialog.close();
            await onSaved();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
            submit.disabled = false;
        }
    });
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(close, form);
    document.body.append(dialog);
    dialog.showModal();
    titleInput.focus();
};

const showPhotoManagement = async () => {
    const data = await request('/api/admin/v1/photo-albums');
    let pages = [];
    try {
        pages = (await request('/api/admin/v1/pages')).items;
    } catch {
        pages = [];
    }
    const editable = canEditModule('photos');
    const heading = sectionHeading('Fotos', 'Fotoalben, Videos und Ergebnislisten zu Veranstaltungen nach Jahren pflegen');
    if (editable) {
        const create = element('button', {className: 'button', text: '＋ Neuer Eintrag', attributes: {type: 'button'}});
        create.addEventListener('click', () => openPhotoAlbumDialog(null, pages, showPhotoManagement));
        heading.append(create);
    }
    const years = data.items.reduce((groups, item) => {
        if (!groups.has(item.year)) groups.set(item.year, []);
        groups.get(item.year).push(item);

        return groups;
    }, new Map());
    const currentYear = new Date().getFullYear();
    const renderRow = (item) => {
        const row = element('button', {
            className: `activity-list-row${item.visible ? '' : ' is-inactive'}`,
            attributes: {type: 'button', ...(editable ? {} : {disabled: 'disabled'})},
            children: [
                element('span', {className: 'activity-list-copy', children: [
                    element('strong', {text: item.title}),
                    element('small', {text: `${formatDateDE(item.date)} · ${item.actions.length ? item.actions.map((action) => action.label).join(', ') : 'ohne Aktionsbutton'}`}),
                ]}),
                ...(item.visible ? [] : [element('span', {className: 'status-badge status-inactive', text: 'Ausgeblendet'})]),
                ...(editable ? [element('span', {className: 'activity-list-edit', text: 'Bearbeiten ›'})] : []),
            ],
        });
        if (editable) row.addEventListener('click', () => openPhotoAlbumDialog(item, pages, showPhotoManagement));

        return row;
    };
    const groups = [...years.entries()].sort(([first], [second]) => second - first).map(([year, items]) => {
        const group = element('details', {className: 'event-helper-archive photo-album-year', children: [
            element('summary', {children: [
                element('strong', {text: String(year)}),
                element('span', {className: 'status-badge', text: String(items.length)}),
            ]}),
            element('div', {className: 'event-helper-archive-list', children: [element('div', {className: 'activity-list', children: items.map(renderRow)})]}),
        ]});
        if (year >= currentYear - 1) group.open = true;

        return group;
    });

    workspace.replaceChildren(
        heading,
        element('div', {className: 'event-helper-groups', children: groups.length ? groups : [emptyState('Noch keine Foto-Einträge angelegt.')]}),
    );
};

export {showPhotoManagement};
