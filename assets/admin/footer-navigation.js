// Unterreiter „Footer“ unter „Seiten“: welche Seiten in welcher Reihenfolge im Footer aller
// öffentlichen Seiten stehen. „Redaktion“ ist keine CMS-Seite und steht immer fest am Ende.
// Änderungen sind ohne Freigabe sofort online (siehe `PageAuthorizationService::assertCanEditFooterNavigation`).

import {element, formMessage, request, toast} from '../core.js';
import {sectionHeading} from './ui.js';
import {canEditFooterNavigation} from './session.js';

const renderFooterNavigation = async (mount) => {
    const data = await request('/api/admin/v1/footer-navigation');
    const editable = canEditFooterNavigation();
    let pages = [...data.pages];

    const list = element('div', {className: 'social-media-links footer-navigation-pages'});
    const addSelect = element('select', {attributes: {'aria-label': 'Seite zum Footer hinzufügen'}});
    const addButton = element('button', {className: 'secondary-button', text: '＋ Hinzufügen', attributes: {type: 'button'}});
    const addRow = element('div', {className: 'social-media-add', children: [addSelect, addButton]});
    const message = formMessage();

    const renderPages = () => {
        list.replaceChildren(...pages.map((page, index) => {
            const move = (offset, text, label) => {
                const button = element('button', {className: 'tree-icon-button', text, attributes: {type: 'button', 'aria-label': label}});
                button.disabled = !editable || !pages[index + offset];
                button.addEventListener('click', () => {
                    [pages[index], pages[index + offset]] = [pages[index + offset], pages[index]];
                    renderPages();
                });

                return button;
            };
            const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', 'aria-label': `${page.label} aus dem Footer entfernen`}});
            remove.disabled = !editable;
            remove.addEventListener('click', () => {
                pages = pages.filter((entry) => entry !== page);
                renderPages();
            });

            return element('div', {className: 'social-media-link-row footer-navigation-row', children: [
                element('div', {className: 'footer-navigation-page', children: [
                    element('strong', {text: page.label}),
                    element('small', {text: `/seite/${page.slug}`}),
                    ...(page.online ? [] : [element('small', {className: 'footer-navigation-offline', text: 'Nicht veröffentlicht oder ausgeblendet – erscheint erst danach im Footer.'})]),
                ]}),
                element('div', {className: 'social-media-link-actions', children: [
                    move(-1, '↑', `${page.label} nach oben`),
                    move(1, '↓', `${page.label} nach unten`),
                    remove,
                ]}),
            ]});
        }));
        if (!pages.length) list.append(element('p', {className: 'empty-copy', text: 'Noch keine Seite im Footer.'}));
        list.append(element('div', {className: 'social-media-link-row footer-navigation-row footer-navigation-fixed', children: [
            element('div', {className: 'footer-navigation-page', children: [
                element('strong', {text: 'Redaktion'}),
                element('small', {text: 'Steht immer fest am Ende.'}),
            ]}),
        ]}));
        const used = new Set(pages.map((page) => page.id));
        const available = data.available.filter((page) => !used.has(page.id));
        addSelect.replaceChildren(...available.map((page) => element('option', {text: `${page.label} (/seite/${page.slug})`, attributes: {value: page.id}})));
        addRow.hidden = !editable || !available.length;
    };
    addButton.addEventListener('click', () => {
        const page = data.available.find((candidate) => candidate.id === addSelect.value);
        if (!page) return;
        pages.push(page);
        renderPages();
    });
    renderPages();

    const actions = [];
    if (editable) {
        const save = element('button', {className: 'button', text: 'Speichern', attributes: {type: 'button'}});
        save.addEventListener('click', async () => {
            save.disabled = true;
            message.textContent = '';
            try {
                await request('/api/admin/v1/footer-navigation', {method: 'PUT', body: JSON.stringify({pageIds: pages.map((page) => page.id)})});
                toast('Der Footer wurde gespeichert und ist sofort online.');
                await mount();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
                save.disabled = false;
            }
        });
        actions.push(save);
    }

    return [
        sectionHeading('Footer', 'Seiten im Footer aller Seiten und ihre Reihenfolge'),
        element('article', {className: 'management-card social-media-editor', children: [
            ...(editable ? [] : [element('p', {className: 'field-hint', text: 'Ändern dürfen nur Redakteure mit Veröffentlichungsrecht für alle Seiten, da der Footer ohne Freigabe sofort online ist.'})]),
            element('p', {className: 'field-hint', text: 'Angezeigt wird die Navigationsbezeichnung der Seite. Eine Seite kann zugleich in der Hauptnavigation stehen.'}),
            list,
            addRow,
            ...(actions.length ? [element('div', {className: 'confirm-dialog-actions', children: actions})] : []),
            message,
        ]}),
    ];
};

export {renderFooterNavigation};
