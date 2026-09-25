// Seitenerweiterung „Fotos“ (Blocktyp `extension`, Schlüssel `photo_albums`): Fotoarchiv aus
// `GET /api/public/v1/photo-albums`, nach Jahren gruppiert (neueste zuerst). Jedes Jahr ist
// aufklappbar; die zwei neuesten Jahre sind geöffnet.

import {element, pageHref, request} from '../core.js';

const OPEN_YEARS = 2;

const renderAction = (action) => {
    const link = element('a', {className: 'secondary-button photo-album-action', text: action.label});
    const setHref = (href) => {
        link.href = href;
        link.hidden = false;
        if (/^https?:\/\//i.test(href)) {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
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

const renderAlbum = (album) => element('li', {className: 'photo-album', children: [
    element('span', {className: 'photo-album-title', text: album.title}),
    ...(album.actions.length ? [element('span', {className: 'photo-album-actions', children: album.actions.map(renderAction)})] : []),
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
