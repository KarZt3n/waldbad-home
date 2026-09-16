// Generische, session-unabhängige Bau-/Bedien-Elemente für die Redaktion — von jedem
// `admin/*.js`-Bereich genutzt (Abschnittsüberschrift, Leerzustand, Pulldown-Menü, Suchfeld,
// Aktions-Button mit Bestätigung).

import {confirmAction, element, request, toast} from '../core.js';

const sectionHeading = (title, description) => element('header', {className: 'section-heading', children: [
    element('p', {className: 'eyebrow', text: 'Redaktion'}), element('h2', {text: title}), element('p', {text: description}),
]});

const emptyState = (text) => element('p', {className: 'empty-copy', text});

// Generisches Pulldown-Menü (natives <details>/<summary>, kein eigener Öffnen/Schließen-Zustand
// nötig) für mehrere Aktionen unter einem Sammelbegriff, z. B. "Datenbank" mit Import/Export.
// Schließt sich beim Klick auf einen Menüpunkt sowie automatisch, sobald ein anderes Menü dieser
// Art auf derselben Seite geöffnet wird.
const actionMenu = (label, items) => {
    const menu = element('details', {className: 'action-menu'});
    const summary = element('summary', {className: 'action-menu-toggle', text: `${label} ▾`});
    const popover = element('div', {className: 'action-menu-popover', children: items.map((item) => {
        const button = element('button', {className: 'action-menu-item', text: item.label, attributes: {type: 'button'}});
        button.addEventListener('click', () => {
            menu.removeAttribute('open');
            item.run();
        });

        return button;
    })});
    // Schließt das Menü bei einem Klick außerhalb — der Klick auf den Umschalter selbst zählt
    // wegen `menu.contains()` nicht als „außerhalb“, öffnet das Menü also nicht sofort wieder zu.
    const closeOnOutsideClick = (event) => {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    };
    menu.addEventListener('toggle', () => {
        if (menu.open) {
            document.querySelectorAll('.action-menu[open]').forEach((other) => {
                if (other !== menu) other.removeAttribute('open');
            });
            document.addEventListener('click', closeOnOutsideClick);
        } else {
            document.removeEventListener('click', closeOnOutsideClick);
        }
    });
    menu.append(summary, popover);

    return menu;
};

// Suchfeld mit Icon und Rücksetzen-Button (nur sichtbar, solange Text eingegeben ist). Löst
// `onSearch(value)` beim Bestätigen (Enter/Fokus verlassen) sowie sofort beim Zurücksetzen aus.
const searchField = (placeholder, value, onSearch) => {
    const input = element('input', {attributes: {type: 'search', placeholder, 'aria-label': placeholder}});
    input.value = value;
    const clear = element('button', {className: 'search-field-clear', text: '×', attributes: {type: 'button', 'aria-label': 'Suche zurücksetzen'}});
    const updateClearVisibility = () => { clear.hidden = input.value === ''; };
    input.addEventListener('input', updateClearVisibility);
    input.addEventListener('change', () => onSearch(input.value.trim()));
    clear.addEventListener('click', () => {
        input.value = '';
        updateClearVisibility();
        input.focus();
        onSearch('');
    });
    updateClearVisibility();

    return element('div', {className: 'search-field', children: [
        element('span', {className: 'search-field-icon', text: '🔍', attributes: {'aria-hidden': 'true'}}),
        input,
        clear,
    ]});
};

const actionButton = (label, url, refresh, className = 'secondary-button', options = {}) => {
    const button = element('button', {className, text: label, attributes: {type: 'button'}});
    button.addEventListener('click', async () => {
        if (options.confirm) {
            const confirmed = await confirmAction(options.confirm.title, options.confirm.description, options.confirm.label || label);
            if (!confirmed) return;
        }
        button.disabled = true;
        try {
            await request(url, {method: 'POST'});
            toast(options.success || `${label} wurde ausgeführt.`);
            await refresh();
        } catch (error) {
            toast(error.message, 'error');
            button.disabled = false;
        }
    });
    return button;
};

export {sectionHeading, emptyState, actionMenu, searchField, actionButton};
