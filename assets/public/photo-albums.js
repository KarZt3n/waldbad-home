// Seitenerweiterung „Fotos“ (Blocktyp `extension`, Schlüssel `photo_albums`): Fotoarchiv aus
// `GET /api/public/v1/photo-albums`, nach Jahren gruppiert (neueste zuerst). Jedes Jahr ist
// aufklappbar; die zwei neuesten Jahre sind geöffnet.

import {element, pageHref, request} from '../core.js';

const OPEN_YEARS = 2;
const formatAlbumDate = (isoDate) => new Date(isoDate + 'T00:00:00').toLocaleDateString('de-DE', {day: '2-digit', month: '2-digit', year: 'numeric'});

const renderAction = (action) => {
    const link = element('a', {className: 'secondary-button photo-album-action', children: [element('span', {text: action.label})]});
    const setHref = (href) => {
        link.href = href;
        link.hidden = false;
        if (/^https?:\/\//i.test(href)) {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            // Nur im Aktionsmenü auf schmalen Bildschirmen sichtbar (siehe CSS).
            link.append(element('span', {className: 'photo-album-action-external', text: '↗', attributes: {'aria-hidden': 'true'}}));
        }
    };
    if (action.url) {
        setHref(action.url);
    } else {
        link.hidden = true;
        request('/api/public/v1/pages/id/' + encodeURIComponent(action.pageId))
            .then((page) => setHref(pageHref(page.slug)))
            .catch(() => link.remove());
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

const renderAlbum = (album) => element('li', {className: 'photo-album', children: [
    element('span', {className: 'photo-album-heading', children: [
        element('span', {className: 'photo-album-title', text: album.title}),
        element('span', {className: 'photo-album-date', text: formatAlbumDate(album.date)}),
    ]}),
    ...(album.actions.length ? [renderActions(album)] : []),
]});

const renderPhotoAlbumsExtension = (preview = false) => {
    const container = element('section', {className: 'photo-albums-extension', attributes: {'aria-label': 'Fotoarchiv', 'aria-live': 'polite'}});
    if (preview) {
        container.append(element('p', {className: 'empty-copy', text: 'Hier erscheint im Frontend das Fotoarchiv, nach Jahren gruppiert.'}));
        return container;
    }
    container.append(element('p', {className: 'empty-copy', text: 'Fotoarchiv wird geladen …'}));
    request('/api/public/v1/photo-albums').then((data) => {
        if (!data.items.length) {
            container.replaceChildren(element('p', {className: 'empty-copy', text: 'Noch keine Fotos vorhanden.'}));
            return;
        }
        const years = data.items.reduce((groups, album) => {
            if (!groups.has(album.year)) groups.set(album.year, []);
            groups.get(album.year).push(album);

            return groups;
        }, new Map());
        container.replaceChildren(...[...years.entries()]
            .sort(([first], [second]) => second - first)
            .map(([year, albums], index) => {
                const group = element('details', {className: 'photo-album-year', children: [
                    element('summary', {children: [
                        element('span', {className: 'photo-album-year-label', text: String(year)}),
                        element('span', {className: 'photo-album-year-count', text: `${albums.length} ${albums.length === 1 ? 'Eintrag' : 'Einträge'}`}),
                    ]}),
                    element('ul', {className: 'photo-album-list', children: albums.map(renderAlbum)}),
                ]});
                group.open = index < OPEN_YEARS;

                return group;
            }));
    }).catch(() => {
        container.replaceChildren(element('p', {className: 'embedded-page-error', text: 'Das Fotoarchiv konnte nicht geladen werden.'}));
    });

    return container;
};

export {renderPhotoAlbumsExtension};
