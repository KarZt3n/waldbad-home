// Rendert Seiteninhalt (Content-Blöcke) aus den Block-Daten der Seiten-API — genutzt sowohl von der
// öffentlichen Website (`public.js`, echte Seitenanzeige) als auch von der Admin-Seitenvorschau
// (`admin/pages.js`, `openPagePreview`): beide zeigen exakt denselben Rendering-Pfad, damit die
// Vorschau zuverlässig dem späteren öffentlichen Ergebnis entspricht.

import {element, pageHref, request, toast} from './core.js';

// Umfangreiche, nur auf einzelnen Seiten genutzte Erweiterungen (Sauna, Fotoarchiv, Mitgliedsantrag)
// werden erst geladen, wenn eine Seite den Block tatsächlich enthält. Der Platzhalter ist sofort im
// Seitenaufbau und bekommt den Inhalt, sobald das Modul da ist.
const lazyExtension = (load) => {
    const slot = element('div', {className: 'extension-slot', attributes: {'aria-busy': 'true'}});
    load()
        .then((node) => slot.replaceChildren(node))
        .catch((error) => slot.replaceChildren(element('p', {className: 'failure-message', text: `Dieser Inhalt konnte nicht geladen werden: ${error.message}`})))
        .finally(() => slot.removeAttribute('aria-busy'));

    return slot;
};

const renderImageSource = (source) => source
    ? element('figcaption', {className: 'image-source', text: `Bildquelle: ${source}`})
    : null;
const eventScheduleToBlockShape = (item, showImages) => ({
    type: 'event',
    content: item.content,
    mediaUrl: showImages ? item.mediaUrl : null,
    mediaAlt: showImages ? item.mediaAlt : null,
    mediaSource: showImages ? item.mediaSource : null,
    layout: item.layout,
    imageWidthPercent: item.imageWidthPercent,
    verticalAlignment: item.verticalAlignment,
    textAlignment: item.textAlignment,
    imageFit: item.imageFit,
    eventTitle: item.title,
    eventDate: item.date,
    eventTime: item.time,
    eventIdentifier: item.id,
    eventHelpEnabled: item.helpEnabled,
    eventHelpButtonLabel: item.helpButtonLabel,
    eventCallToActions: item.callToActions,
});
const renderEventScheduleExtension = (kind, mode, extensionOptions, context) => {
    const container = element('section', {className: 'event-schedule-extension', attributes: {'aria-live': 'polite'}});
    const emptyMessage = mode === 'next'
        ? (kind === 'any' ? 'Aktuell ist keine weitere Veranstaltung oder Arbeitseinsatz geplant.'
            : kind === 'work_assignment' ? 'Aktuell ist kein weiterer Arbeitseinsatz geplant.' : 'Aktuell ist keine weitere Veranstaltung geplant.')
        : mode === 'from_current_year' ? 'Aktuell sind keine Veranstaltungen ab diesem Jahr eingetragen.'
        : (kind === 'work_assignment' ? 'Aktuell sind keine Arbeitseinsätze für dieses Jahr eingetragen.' : 'Aktuell sind keine Veranstaltungen für dieses Jahr eingetragen.');
    if (context.isPreview) {
        container.append(element('p', {className: 'empty-copy', text: emptyMessage}));
        return container;
    }
    const endpoint = mode === 'next'
        ? `/api/public/v1/events/next?kind=${encodeURIComponent(kind)}`
        : `/api/public/v1/events?kind=${encodeURIComponent(kind)}&period=${encodeURIComponent(mode)}`;
    request(endpoint).then((data) => {
        const items = mode === 'next' ? (data.item ? [data.item] : []) : (data.items || []);
        if (!items.length) {
            container.replaceChildren(element('p', {className: 'empty-copy', text: emptyMessage}));
            return;
        }
        const showImages = extensionOptions?.showImages !== false;
        container.replaceChildren(...items.map((item) => renderPublicBlock(eventScheduleToBlockShape(item, showImages), context)));
    }).catch(() => {
        container.replaceChildren(...(context.showEmbedErrors
            ? [element('p', {className: 'embedded-page-error', text: 'Die Veranstaltungen konnten nicht geladen werden.'})]
            : []));
    });

    return container;
};
const renderActionButtons = (actions, context) => {
    const row = element('div', {className: 'content-action-row'});
    (Array.isArray(actions) ? actions : []).forEach((action) => {
        if (!action?.label || (!action.url && !action.pageId)) return;

        const link = element('a', {className: 'button secondary-button', text: action.label});
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
            const localPage = context.pagesById?.get(action.pageId);
            const pageRequest = localPage
                ? Promise.resolve(localPage)
                : request('/api/public/v1/pages/id/' + encodeURIComponent(action.pageId));
            pageRequest.then((targetPage) => setHref(pageHref(targetPage.slug))).catch(() => link.remove());
        }
        row.append(link);
    });

    return row.childElementCount > 0 ? row : null;
};
const renderPublicBlock = (block, context = {visited: new Set(), pagesById: null, showEmbedErrors: false, isPreview: false}) => {
    if (block.type === 'extension' && block.extensionKey === 'membership_application') {
        return lazyExtension(async () => (await import('./public/membership-application.js')).renderMembershipApplicationForm(context.isPreview === true));
    }
    if (block.type === 'extension' && block.extensionKey === 'photo_albums') {
        return lazyExtension(async () => (await import('./public/photo-albums.js')).renderPhotoAlbumsExtension(context.isPreview === true));
    }
    if (block.type === 'extension' && block.extensionKey === 'sauna') {
        return lazyExtension(async () => (await import('./public/sauna.js')).renderSaunaExtension(context.isPreview === true));
    }
    if (block.type === 'extension' && ['events_current_year', 'events_from_current_year', 'work_assignments_current_year', 'next_event', 'next_work_assignment', 'next_event_or_work_assignment'].includes(block.extensionKey)) {
        const kind = block.extensionKey === 'next_event_or_work_assignment' ? 'any'
            : (block.extensionKey.startsWith('work_assignments') || block.extensionKey === 'next_work_assignment' ? 'work_assignment' : 'event');
        const mode = block.extensionKey.startsWith('next_') ? 'next'
            : (block.extensionKey === 'events_from_current_year' ? 'from_current_year' : 'current_year');

        return renderEventScheduleExtension(kind, mode, block.extensionOptions, context);
    }
    if (block.type === 'page_teaser') {
        const container = element('section', {className: 'page-teaser-loading', attributes: {'aria-live': 'polite'}});
        if (!block.embeddedPageId) {
            if (context.showEmbedErrors) container.append(element('p', {className: 'embedded-page-error', text: 'Für den Seitenteaser wurde keine Zielseite ausgewählt.'}));
            return container;
        }

        const localPage = context.pagesById?.get(block.embeddedPageId);
        const pageRequest = localPage
            ? Promise.resolve(localPage)
            : request('/api/public/v1/pages/id/' + encodeURIComponent(block.embeddedPageId));
        pageRequest.then((targetPage) => {
            const href = pageHref(targetPage.slug);
            const copy = element('div', {className: 'image-text-copy page-teaser-copy', children: [
                element('h2', {text: targetPage.title}),
            ]});
            if (block.content) {
                const teaserText = element('div', {className: 'rich-html'});
                teaserText.innerHTML = block.content;
                copy.append(teaserText);
            }
            copy.append(element('a', {className: 'button', text: block.linkLabel || 'Mehr erfahren', attributes: {href}}));
            const layout = block.layout === 'image_right' ? 'image-right' : 'image-left';
            const imageWidth = Number.isInteger(block.imageWidthPercent) ? block.imageWidthPercent : 50;
            const verticalAlignment = block.verticalAlignment || 'center';
            const textAlignment = block.textAlignment || 'left';
            const imageFit = block.imageFit || 'cover';
            container.className = `image-text page-teaser ${layout} align-${verticalAlignment} text-${textAlignment} fit-${imageFit}${block.mediaUrl ? ' has-image' : ''}`;
            container.style.setProperty('--image-width', `${imageWidth}%`);
            container.replaceChildren(
                ...(block.mediaUrl ? [element('figure', {
                    className: 'image-text-media',
                    children: [
                        element('a', {
                            attributes: {href, 'aria-label': targetPage.title},
                            children: [element('img', {attributes: {src: block.mediaUrl, alt: block.mediaAlt || '', loading: 'lazy'}})],
                        }),
                        ...(block.mediaSource ? [renderImageSource(block.mediaSource)] : []),
                    ],
                })] : []),
                copy,
            );
        }).catch(() => {
            container.replaceChildren(...(context.showEmbedErrors
                ? [element('p', {className: 'embedded-page-error', text: 'Die Zielseite des Teasers ist nicht veröffentlicht oder nicht sichtbar.'})]
                : []));
        });

        return container;
    }
    if (block.type === 'embedded_page') {
        const container = element('section', {className: 'embedded-page', attributes: {'aria-live': 'polite'}});
        if (!block.embeddedPageId || context.visited.has(block.embeddedPageId)) {
            if (context.showEmbedErrors) container.append(element('p', {className: 'embedded-page-error', text: 'Die eingebettete Seite kann nicht angezeigt werden.'}));
            return container;
        }

        const localPage = context.pagesById?.get(block.embeddedPageId);
        const pageRequest = localPage
            ? Promise.resolve(localPage)
            : request('/api/public/v1/pages/id/' + encodeURIComponent(block.embeddedPageId));
        pageRequest.then((page) => {
            const visited = new Set(context.visited);
            visited.add(block.embeddedPageId);
            container.replaceChildren(...page.blocks.map((nestedBlock) => renderPublicBlock(nestedBlock, {...context, visited})));
        }).catch(() => {
            container.replaceChildren(...(context.showEmbedErrors
                ? [element('p', {className: 'embedded-page-error', text: 'Die eingebettete Seite ist nicht verfügbar.'})]
                : []));
        });

        return container;
    }
    if (block.type === 'event_reference') {
        const container = element('section', {className: 'embedded-event', attributes: {'aria-live': 'polite'}});
        if (!block.embeddedPageId || !block.eventIdentifier) {
            if (context.showEmbedErrors) container.append(element('p', {className: 'embedded-page-error', text: 'Die eingebettete Veranstaltung wurde nicht ausgewählt.'}));
            return container;
        }

        const localPage = context.pagesById?.get(block.embeddedPageId);
        const pageRequest = localPage
            ? Promise.resolve(localPage)
            : request('/api/public/v1/pages/id/' + encodeURIComponent(block.embeddedPageId));
        pageRequest.then((page) => {
            const event = page.blocks.find((candidate) => candidate.type === 'event' && candidate.eventIdentifier === block.eventIdentifier);
            if (!event) {
                container.replaceChildren(...(context.showEmbedErrors
                    ? [element('p', {className: 'embedded-page-error', text: 'Die ausgewählte Veranstaltung ist nicht mehr verfügbar.'})]
                    : []));
                return;
            }
            container.replaceChildren(renderPublicBlock({
                ...event,
                mediaUrl: block.mediaUrl || event.mediaUrl,
                mediaAlt: block.mediaUrl ? block.mediaAlt : event.mediaAlt,
                mediaSource: block.mediaUrl ? block.mediaSource : (block.mediaSource || event.mediaSource),
                layout: block.layout || event.layout,
                imageWidthPercent: block.imageWidthPercent || event.imageWidthPercent,
                verticalAlignment: block.verticalAlignment || event.verticalAlignment,
                textAlignment: block.textAlignment || event.textAlignment,
                imageFit: block.imageFit || event.imageFit,
            }, context));
        }).catch(() => {
            container.replaceChildren(...(context.showEmbedErrors
                ? [element('p', {className: 'embedded-page-error', text: 'Die ausgewählte Veranstaltung ist nicht veröffentlicht oder nicht sichtbar.'})]
                : []));
        });

        return container;
    }
    if (block.type === 'event') {
        const title = element('h2', {text: block.eventTitle || ''});
        const details = element('div', {className: 'event-details'});
        details.innerHTML = block.content;
        const dateParts = (block.eventDate || '').split('-').map(Number);
        const formattedDate = dateParts.length === 3
            ? new Intl.DateTimeFormat('de-DE', {day: '2-digit', month: 'long', year: 'numeric'}).format(new Date(dateParts[0], dateParts[1] - 1, dateParts[2]))
            : block.eventDate;
        const copy = element('div', {className: 'event-copy', children: [
            element('time', {className: 'event-date', text: `${formattedDate} · ${block.eventTime} Uhr`, attributes: {datetime: `${block.eventDate}T${block.eventTime}`}}),
            title,
            ...(block.content ? [details] : []),
        ]});
        const actionRow = element('div', {className: 'event-action-row'});
        if (block.eventHelpEnabled && block.eventIdentifier) {
            const help = element('button', {
                className: 'button event-help-button',
                text: block.eventHelpButtonLabel || 'Ich möchte helfen!',
                attributes: {type: 'button', ...(context.isPreview ? {disabled: 'disabled', title: 'In der Vorschau nicht verfügbar'} : {})},
            });
            if (!context.isPreview) help.addEventListener('click', async () => {
                try {
                    const {openEventHelpDialog} = await import('./public/event-help.js');
                    await openEventHelpDialog(block);
                } catch (error) {
                    toast(error.message, 'error');
                }
            });
            // 24 Stunden nach Beginn der Veranstaltung ist keine Helferanmeldung mehr möglich; bleibt
            // die Seite über diesen Zeitpunkt hinaus offen, wird der Button dann deaktiviert.
            const helpClosesAt = new Date(`${block.eventDate}T${block.eventTime || '00:00'}`).getTime() + 24 * 60 * 60 * 1000;
            const closeHelp = () => {
                help.disabled = true;
                help.title = 'Die Helferanmeldung ist geschlossen.';
            };
            if (!context.isPreview && !Number.isNaN(helpClosesAt)) {
                const remaining = helpClosesAt - Date.now();
                if (remaining <= 0) closeHelp();
                // setTimeout verträgt höchstens ~24,8 Tage; weiter entfernte Termine prüft der nächste Seitenaufruf.
                else if (remaining < 2 ** 31) setTimeout(closeHelp, remaining);
            }
            actionRow.append(help);
        }
        (Array.isArray(block.eventCallToActions) ? block.eventCallToActions : []).forEach((action) => {
            if (!action?.label || (!action.url && !action.pageId)) return;
            const link = element('a', {className: 'button secondary-button event-call-action', text: action.label});
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
                const localPage = context.pagesById?.get(action.pageId);
                const pageRequest = localPage
                    ? Promise.resolve(localPage)
                    : request('/api/public/v1/pages/id/' + encodeURIComponent(action.pageId));
                pageRequest.then((targetPage) => setHref(pageHref(targetPage.slug))).catch(() => link.remove());
            }
            actionRow.append(link);
        });
        if (actionRow.childElementCount > 0) copy.append(actionRow);
        const layout = ['image_left', 'image_right', 'image_top'].includes(block.layout) ? block.layout : 'image_left';
        const imageWidth = Number.isInteger(block.imageWidthPercent) ? block.imageWidthPercent : 32;
        const verticalAlignment = ['top', 'center', 'bottom'].includes(block.verticalAlignment) ? block.verticalAlignment : 'center';
        const textAlignment = ['left', 'center', 'right'].includes(block.textAlignment) ? block.textAlignment : 'left';
        const imageFit = ['cover', 'contain'].includes(block.imageFit) ? block.imageFit : 'cover';
        const media = block.mediaUrl ? element('figure', {className: 'event-media', children: [
            element('img', {attributes: {src: block.mediaUrl, alt: block.mediaAlt || '', loading: 'lazy'}}),
            ...(block.mediaSource ? [renderImageSource(block.mediaSource)] : []),
        ]}) : null;
        return element('article', {
            className: `event-block${block.mediaUrl ? ` has-image ${layout} align-${verticalAlignment} text-${textAlignment} fit-${imageFit}` : ''}`,
            attributes: {style: `--event-image-width: 24%`},
            children: [
            ...(media ? [media] : []),
            copy,
        ]});
    }
    if (block.type === 'feature_collection') {
        const columns = Number.isInteger(block.collectionColumns)
            ? Math.min(4, Math.max(1, block.collectionColumns))
            : 3;
        const heading = element('h2', {className: 'feature-collection-heading'});
        heading.innerHTML = block.content;
        const items = Array.isArray(block.collectionItems) ? block.collectionItems : [];

        return element('section', {className: 'feature-collection', children: [
            heading,
            element('div', {
                className: 'feature-collection-grid',
                attributes: {style: `--collection-columns: ${columns}`},
                children: items.map((item) => {
                    const title = element('h3');
                    title.innerHTML = item.title || '';
                    const copy = element('div', {className: 'feature-collection-copy'});
                    copy.innerHTML = item.content || '';
                    return element('article', {className: `feature-collection-item${item.mediaUrl ? ' has-image' : ''}`, children: [
                        ...(item.mediaUrl ? [element('figure', {className: 'feature-collection-media', children: [
                            element('img', {attributes: {src: item.mediaUrl, alt: item.mediaAlt || '', loading: 'lazy'}}),
                            ...(item.mediaSource ? [renderImageSource(item.mediaSource)] : []),
                        ]})] : []),
                        element('div', {className: 'feature-collection-body', children: [
                            title,
                            ...(item.content ? [copy] : []),
                        ]}),
                    ]});
                }),
            }),
        ]});
    }
    if (block.type === 'heading') {
        const heading = element('h2');
        heading.innerHTML = block.content;
        return heading;
    }
    if (block.type === 'rich_text' || block.type === 'custom_html') {
        const container = element('div', {className: block.type === 'custom_html' ? 'custom-html' : 'rich-html'});
        container.innerHTML = block.content;
        const actions = block.type === 'rich_text' ? renderActionButtons(block.actionButtons, context) : null;
        return actions === null
            ? container
            : element('section', {className: 'text-content-block', children: [container, actions]});
    }
    if (block.type === 'image_text') {
        const copy = element('div', {className: 'image-text-copy'});
        copy.innerHTML = block.content;
        const imageWidth = Number.isInteger(block.imageWidthPercent) ? block.imageWidthPercent : 50;
        const verticalAlignment = block.verticalAlignment || 'center';
        const textAlignment = block.textAlignment || 'left';
        const imageFit = block.imageFit || 'cover';
        const image = element('img', {attributes: {src: block.mediaUrl, alt: block.mediaAlt || '', loading: 'lazy'}});
        const imageContent = block.linkUrl
            ? element('a', {
                attributes: {href: block.linkUrl, target: '_blank', rel: 'noopener noreferrer'},
                children: [image],
            })
            : image;
        const media = element('figure', {className: 'image-text-media', children: [
            imageContent,
            ...(block.mediaSource ? [renderImageSource(block.mediaSource)] : []),
        ]});
        const actions = renderActionButtons(block.actionButtons, context);
        if (actions !== null) copy.append(actions);
        return element('section', {
            className: `image-text ${block.layout === 'image_right' ? 'image-right' : 'image-left'} align-${verticalAlignment} text-${textAlignment} fit-${imageFit}`,
            attributes: {style: `--image-width: ${imageWidth}%`},
            children: [
                media,
                copy,
            ],
        });
    }
    if (block.type === 'image') {
        const imageWidth = Number.isInteger(block.imageWidthPercent) ? block.imageWidthPercent : 100;
        const imageAlignment = ['left', 'center', 'right'].includes(block.layout) ? block.layout : 'center';
        const figure = element('figure', {
            className: `content-image align-${imageAlignment}`,
            attributes: {style: `--image-width: ${imageWidth}%`},
            children: [
                element('img', {attributes: {src: block.mediaUrl, alt: block.mediaAlt || '', loading: 'lazy'}}),
                ...(block.mediaSource ? [renderImageSource(block.mediaSource)] : []),
            ],
        });
        const actions = renderActionButtons(block.actionButtons, context);
        return actions === null
            ? figure
            : element('section', {className: `image-content-block align-${imageAlignment}`, children: [figure, actions]});
    }
    if (block.type === 'alert') {
        const alertContent = element('div');
        alertContent.innerHTML = block.content;
        return element('aside', {
            className: 'notice',
            attributes: {role: 'status'},
            children: [alertContent],
        });
    }
    if (block.type === 'call_to_action') {
        const callToActionContent = element('div');
        callToActionContent.innerHTML = block.content;
        return element('div', {
            className: 'cta-row',
            children: [
                callToActionContent,
                // In der Vorschau eines Entwurfs kann der Link noch fehlen.
                ...(block.linkUrl && block.linkLabel ? [element('a', {className: 'button', text: block.linkLabel, attributes: {href: block.linkUrl, target:'_blank'}})] : []),
            ],
        });
    }

    return element('p', {className: 'prose', text: block.content});
};
const renderContentCard = (page, context) => element('article', {
    className: 'content-card',
    children: page.blocks.map((block) => renderPublicBlock(block, context)),
});

export {
    renderImageSource,
    eventScheduleToBlockShape,
    renderEventScheduleExtension,
    renderPublicBlock,
    renderContentCard,
};
