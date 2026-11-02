// Seitenerweiterung „Fotos“ (Blocktyp `extension`, Schlüssel `photo_albums`): Fotoarchiv nach
// Jahren gruppiert (neueste zuerst). Jedes Jahr ist aufklappbar; die zwei neuesten Jahre sind
// geöffnet, die übrigen werden erst beim Aufklappen geladen.

import {element, pageHref, request} from '../core.js';

const OPEN_YEARS = 2;
const formatAlbumDate = (isoDate) => new Date(isoDate + 'T00:00:00').toLocaleDateString('de-DE', {day: '2-digit', month: '2-digit', year: 'numeric'});

const renderAction = (action) => {
    const href = action.url || pageHref(action.pageSlug);
    const link = element('a', {className: 'secondary-button photo-album-action', attributes: {href}, children: [element('span', {text: action.label})]});
    if (/^https?:\/\//i.test(href)) {
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        // Nur im Aktionsmenü auf schmalen Bildschirmen sichtbar (siehe CSS).
        link.append(element('span', {className: 'photo-album-action-external', text: '↗', attributes: {'aria-hidden': 'true'}}));
    }

    return link;
};

// Auf schmalen Bildschirmen stehen die Buttons nicht neben dem Titel, sondern in einem Aktionsmenü
// hinter einem „⋮“-Button rechts (siehe CSS); auf breiten Bildschirmen ist der Button ausgeblendet.
const renderActions = (album) => {
    const actions = element('span', {className: 'photo-album-actions', children: album.actions.map(renderAction)});
    const toggle = element('button', {
        className: 'photo-album-menu-toggle',
        attributes: {type: 'button', 'aria-expanded': 'false', 'aria-label': `Aktionen zu „${album.title}“ anzeigen`},
        children: [0, 1, 2].map(() => element('span', {className: 'photo-album-menu-dot'})),
    });
    const menu = element('span', {className: 'photo-album-menu', children: [toggle, actions]});
    const setOpen = (open) => {
        menu.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', String(open));
    };
    const closeOnOutsideClick = (event) => {
        if (!menu.contains(event.target)) setOpen(false);
        else document.addEventListener('click', closeOnOutsideClick, {once: true});
    };
    toggle.addEventListener('click', () => {
        const open = !menu.classList.contains('open');
        setOpen(open);
        if (open) setTimeout(() => document.addEventListener('click', closeOnOutsideClick, {once: true}));
    });
    menu.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !menu.classList.contains('open')) return;
        setOpen(false);
        toggle.focus();
    });

    return menu;
};

// Buttons auf nicht (mehr) veröffentlichte Seiten liefert die API ohne `pageSlug`; sie entfallen.
const renderAlbum = (album) => {
    const actions = album.actions.filter((action) => action.url || action.pageSlug);

    return element('li', {className: 'photo-album', children: [
        element('span', {className: 'photo-album-heading', children: [
            element('span', {className: 'photo-album-title', text: album.title}),
            element('span', {className: 'photo-album-date', text: formatAlbumDate(album.date)}),
        ]}),
        ...(actions.length ? [renderActions({...album, actions})] : []),
    ]});
};

const renderYear = ({year, count}, open) => {
    const list = element('ul', {className: 'photo-album-list'});
    const group = element('details', {className: 'photo-album-year', children: [
        element('summary', {children: [
            element('span', {className: 'photo-album-year-label', text: String(year)}),
            element('span', {className: 'photo-album-year-count', text: `${count} ${count === 1 ? 'Eintrag' : 'Einträge'}`}),
        ]}),
        list,
    ]});
    let loading = null;
    const load = () => {
        loading ??= request(`/api/public/v1/photo-albums/years/${year}`)
            .then((data) => list.replaceChildren(...data.items.map(renderAlbum)))
            .catch(() => {
                loading = null;
                list.replaceChildren(element('li', {className: 'embedded-page-error', text: 'Die Einträge konnten nicht geladen werden.'}));
            });
    };
    group.addEventListener('toggle', () => {
        if (group.open) load();
    });
    group.open = open;
    if (open) load();

    return group;
};

const renderPhotoAlbumsExtension = (preview = false) => {
    const container = element('section', {className: 'photo-albums-extension', attributes: {'aria-label': 'Fotoarchiv', 'aria-live': 'polite'}});
    if (preview) {
        container.append(element('p', {className: 'empty-copy', text: 'Hier erscheint im Frontend das Fotoarchiv, nach Jahren gruppiert.'}));
        return container;
    }
    container.append(element('p', {className: 'empty-copy', text: 'Fotoarchiv wird geladen …'}));
    request('/api/public/v1/photo-albums/years').then((data) => {
        if (!data.items.length) {
            container.replaceChildren(element('p', {className: 'empty-copy', text: 'Noch keine Fotos vorhanden.'}));
            return;
        }
        container.replaceChildren(...data.items.map((year, index) => renderYear(year, index < OPEN_YEARS)));
    }).catch(() => {
        container.replaceChildren(element('p', {className: 'embedded-page-error', text: 'Das Fotoarchiv konnte nicht geladen werden.'}));
    });

    return container;
};

export {renderPhotoAlbumsExtension};
