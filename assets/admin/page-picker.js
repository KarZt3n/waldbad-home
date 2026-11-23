// Overlay zur Auswahl einer Seite in derselben Darstellung wie der Seitenbaum unter „Seiten“
// (Symbol, Titel, Statuspunkt, Einrückung der Unterseiten) — überall dort, wo eine Seite gewählt
// wird: Seite einbetten, Seitenteaser, Aktionsbuttons, übergeordnete Seite und Footer.

import {buildPageTree, element} from '../core.js';

const PAGE_STATUS_LABELS = {
    draft: 'Entwurf',
    in_review: 'In Prüfung',
    published: 'Veröffentlicht',
    archived: 'Archiviert',
};

const pageStatusText = (page) => `${PAGE_STATUS_LABELS[page.status] || page.status}${page.publishedAt && page.status !== 'published' ? ' · letzte Version online' : ''}${page.visible ? '' : ' · ausgeblendet'}`;

/**
 * Liefert `{page}` mit der gewählten Seite (bzw. `{page: null}` bei `noneLabel`) oder `null` bei
 * Abbruch. `pages` ist die flache Liste aus `/api/admin/v1/pages`; Seiten aus `disabledIds` bleiben
 * für die Struktur sichtbar, sind aber nicht wählbar (z. B. die aktuelle Seite).
 */
const openPagePickerDialog = ({pages, title, hint = null, selectedId = null, disabledIds = [], noneLabel = null}) => new Promise((resolve) => {
    const disabled = new Set(disabledIds.filter(Boolean));
    const dialog = element('dialog', {className: 'confirm-dialog page-picker-dialog'});
    let chosen = null;
    const choose = (page) => {
        chosen = {page};
        dialog.close();
    };
    const treeButton = ({icon, label, active, className = '', title: buttonTitle = null, status = null}) => element('button', {
        className: `tree-item page-tree-item${active ? ' active' : ''}${className}`,
        attributes: {type: 'button', ...(buttonTitle ? {title: buttonTitle} : {}), ...(active ? {'aria-current': 'true'} : {})},
        children: [
            element('span', {className: 'tree-item-icon', text: icon, attributes: {'aria-hidden': 'true'}}),
            element('span', {className: 'tree-item-name', text: label}),
            ...(status ? [status] : []),
        ],
    });
    const renderLevel = (nodes) => (nodes.length ? [element('ul', {children: nodes.map((page) => {
        const isDisabled = disabled.has(page.id);
        const button = treeButton({
            icon: page.slug === 'startseite' ? '🏠' : '📄',
            label: page.title,
            active: page.id === selectedId,
            className: `${page.visible ? '' : ' is-hidden'}${isDisabled ? ' is-disabled' : ''}`,
            title: `/${page.slug}`,
            status: element('span', {className: `page-status-dot is-${page.status}${page.publishedAt && page.status !== 'published' ? ' has-online-version' : ''}`, attributes: {title: pageStatusText(page), 'aria-label': pageStatusText(page)}}),
        });
        button.disabled = isDisabled;
        button.addEventListener('click', () => choose(pages.find((candidate) => candidate.id === page.id) || null));

        return element('li', {children: [
            element('div', {className: 'page-tree-entry', children: [button]}),
            ...renderLevel(page.children),
        ]});
    })})] : []);
    const levels = renderLevel(buildPageTree(pages));
    if (noneLabel) {
        const none = treeButton({icon: '⌂', label: noneLabel, active: !selectedId});
        none.addEventListener('click', () => choose(null));
        levels.unshift(element('ul', {children: [element('li', {children: [element('div', {className: 'page-tree-entry', children: [none]})]})]}));
    }
    const tree = element('div', {className: 'tree-panel page-tree-nav page-picker-tree', children: levels.length ? levels : [element('p', {className: 'empty-copy', text: 'Noch keine Seiten vorhanden.'})]});
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        dialog.remove();
        resolve(chosen);
    });
    dialog.append(element('div', {className: 'confirm-dialog-content', children: [
        element('h2', {text: title}),
        ...(hint ? [element('p', {className: 'field-hint', text: hint})] : []),
        tree,
        element('div', {className: 'confirm-dialog-actions', children: [cancel]}),
    ]}));
    document.body.append(dialog);
    dialog.showModal();
    (tree.querySelector('.tree-item.active:not(:disabled)') || tree.querySelector('.tree-item:not(:disabled)') || cancel).focus();
});

/**
 * Formularfeld statt Pulldown: zeigt die gewählte Seite und öffnet zum Auswählen/Ändern das
 * Overlay mit dem Seitenbaum. `onChange` erhält die Id der neu gewählten Seite (oder `null` bei
 * `noneLabel`); mit `name` wird der Wert zusätzlich als verstecktes Formularfeld mitgesendet.
 */
const pagePickerField = ({label, pages, value, onChange = () => {}, dialogTitle = 'Seite auswählen', disabledIds = [], hint = null, noneLabel = null, name = null}) => {
    let current = value || null;
    const hidden = name ? element('input', {attributes: {type: 'hidden', name}}) : null;
    const currentLabel = element('span', {className: 'page-picker-current'});
    const chooseButton = element('button', {className: 'secondary-button button-compact', attributes: {type: 'button'}});
    const render = () => {
        const page = pages.find((candidate) => candidate.id === current);
        currentLabel.textContent = page
            ? `${page.title}${page.visible ? '' : ' (ausgeblendet)'}`
            : (current ? 'Ausgewählte Seite ist nicht mehr vorhanden' : (noneLabel || 'Keine Seite ausgewählt'));
        currentLabel.classList.toggle('is-empty', !page);
        chooseButton.textContent = page || noneLabel ? 'Ändern …' : 'Seite auswählen …';
        chooseButton.setAttribute('aria-label', `${label}: ${currentLabel.textContent} – ${chooseButton.textContent}`);
        if (hidden) hidden.value = current || '';
    };
    chooseButton.addEventListener('click', async () => {
        const result = await openPagePickerDialog({pages, title: dialogTitle, hint, selectedId: current, disabledIds, noneLabel});
        if (!result) return;
        current = result.page?.id || null;
        onChange(current);
        render();
        hidden?.dispatchEvent(new Event('change', {bubbles: true}));
    });
    render();

    return element('div', {className: 'field', children: [
        element('span', {text: label}),
        element('div', {className: 'page-picker-field', children: [currentLabel, chooseButton, ...(hidden ? [hidden] : [])]}),
        ...(hint ? [element('small', {text: hint})] : []),
    ]});
};

export {openPagePickerDialog, pagePickerField, pageStatusText};
