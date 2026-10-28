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

// Kontextmenü (Rechtsklick bzw. ☰-Button), z. B. für Mediathek-Ordner und Seitenbaum: schließt bei Auswahl, Klick daneben,
// Escape, Scrollen und Größenänderung. Shift+F10/Menütaste lösen `contextmenu` ebenfalls aus.
let closeOpenMenu = null;
const openContextMenu = (x, y, items, returnFocus = null) => {
    closeOpenMenu?.();
    const menu = element('div', {className: 'context-menu', attributes: {role: 'menu'}});
    const close = () => {
        menu.remove();
        document.removeEventListener('pointerdown', onPointerDown, true);
        document.removeEventListener('keydown', onKeyDown, true);
        window.removeEventListener('scroll', close, true);
        window.removeEventListener('resize', close);
        closeOpenMenu = null;
    };
    const onPointerDown = (event) => {
        if (!menu.contains(event.target)) close();
    };
    const onKeyDown = (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            returnFocus?.focus();
        }
    };
    menu.append(...items.map((item) => {
        const button = element('button', {
            className: `context-menu-item${item.danger ? ' is-danger' : ''}`,
            text: item.label,
            attributes: {type: 'button', role: 'menuitem', ...(item.disabled ? {disabled: 'disabled', title: item.disabled} : {})},
        });
        button.addEventListener('click', () => {
            close();
            item.run();
        });

        return button;
    }));
    document.body.append(menu);
    const {width, height} = menu.getBoundingClientRect();
    menu.style.left = `${Math.max(8, Math.min(x, window.innerWidth - width - 8))}px`;
    menu.style.top = `${Math.max(8, Math.min(y, window.innerHeight - height - 8))}px`;
    document.addEventListener('pointerdown', onPointerDown, true);
    document.addEventListener('keydown', onKeyDown, true);
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
    closeOpenMenu = close;
    menu.querySelector('button:not([disabled])')?.focus();
};
const openContextMenuAtElement = (anchor, items) => {
    const rect = anchor.getBoundingClientRect();
    openContextMenu(rect.right - 200, rect.bottom + 4, items, anchor);
};
// Per Tastatur (Shift+F10/Menütaste) ausgelöste `contextmenu`-Events haben keine Mauskoordinaten;
// das Menü erscheint dann unter dem Element.
const openContextMenuFromEvent = (event, anchor, items) => {
    event.preventDefault();
    event.stopPropagation();
    const fromKeyboard = event.clientX === 0 && event.clientY === 0;
    const rect = anchor.getBoundingClientRect();
    openContextMenu(fromKeyboard ? rect.left + 24 : event.clientX, fromKeyboard ? rect.bottom : event.clientY, items, anchor);
};

// Zweispaltiges Baum-Layout (Mediathek, Seiten) mit ziehbarem ⋮-Griff zwischen Baum und Inhalt.
// Die gezogene Breite gilt nur für diesen Browser; Doppelklick auf den Griff stellt den Standard
// (`defaultWidth`, eine CSS-Spurbreite) wieder her.
const TREE_MIN_WIDTH = 176;
const TREE_MAX_SHARE = 0.6;
const treeLayout = (panel, content, {storageKey, defaultWidth, className = ''}) => {
    const handle = element('div', {
        className: 'tree-resize-handle',
        attributes: {role: 'separator', tabindex: '0', 'aria-orientation': 'vertical', 'aria-label': 'Breite des Baums ändern', title: 'Ziehen, um die Breite zu ändern · Doppelklick setzt sie zurück'},
        children: [element('span', {className: 'tree-resize-grip', text: '⋮', attributes: {'aria-hidden': 'true'}})],
    });
    const layout = element('div', {className: `tree-layout ${className}`.trim(), children: [panel, handle, content]});
    const clamp = (width) => Math.round(Math.max(TREE_MIN_WIDTH, Math.min(width, layout.clientWidth * TREE_MAX_SHARE)));
    const applyWidth = (width) => layout.style.setProperty('--tree-width', width === null ? defaultWidth : `${width}px`);
    const storeWidth = (width) => {
        try {
            if (width === null) window.localStorage.removeItem(storageKey);
            else window.localStorage.setItem(storageKey, String(width));
        } catch {
            // Ohne Speicherzugriff gilt die Breite nur bis zum Neuladen.
        }
    };
    let storedWidth = null;
    try {
        const value = Number.parseInt(window.localStorage.getItem(storageKey) || '', 10);
        storedWidth = Number.isFinite(value) && value >= TREE_MIN_WIDTH ? value : null;
    } catch {
        storedWidth = null;
    }
    applyWidth(storedWidth);

    let pointerId = null;
    handle.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) return;
        event.preventDefault();
        pointerId = event.pointerId;
        handle.setPointerCapture?.(event.pointerId);
        layout.classList.add('is-resizing');
    });
    handle.addEventListener('pointermove', (event) => {
        if (event.pointerId !== pointerId) return;
        applyWidth(clamp(event.clientX - panel.getBoundingClientRect().left));
    });
    const endResize = (event) => {
        if (event.pointerId !== pointerId) return;
        pointerId = null;
        handle.releasePointerCapture?.(event.pointerId);
        layout.classList.remove('is-resizing');
        storeWidth(Math.round(panel.getBoundingClientRect().width));
    };
    handle.addEventListener('pointerup', endResize);
    handle.addEventListener('pointercancel', endResize);
    handle.addEventListener('dblclick', () => {
        applyWidth(null);
        storeWidth(null);
    });
    handle.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        const width = clamp(panel.getBoundingClientRect().width + (event.key === 'ArrowRight' ? 16 : -16));
        applyWidth(width);
        storeWidth(width);
    });

    return layout;
};

// Lade-Hinweis für Ansichtswechsel (Menü, Reiter, Baum): erscheint erst, wenn der Wechsel länger
// als eine Sekunde dauert, damit schnelle Wechsel nicht flackern. Liegt die Ansicht hinter einem
// Dialog (z. B. PIN-Abfrage), zählt die Wartezeit erst ab dessen Schließen.
const NAVIGATION_LOADER_DELAY_MS = 1000;
const DIALOG_POLL_MS = 250;
let pendingNavigations = 0;
let navigationLoaderTimer = null;
let navigationLoader = null;
const showNavigationLoader = () => {
    if (document.querySelector('dialog[open]')) {
        navigationLoaderTimer = window.setTimeout(() => {
            navigationLoaderTimer = document.querySelector('dialog[open]')
                ? window.setTimeout(showNavigationLoader, 0)
                : window.setTimeout(showNavigationLoader, NAVIGATION_LOADER_DELAY_MS);
        }, DIALOG_POLL_MS);
        return;
    }
    const target = document.querySelector('.admin-workspace');
    if (!target) return;
    const rect = target.getBoundingClientRect();
    const top = Math.max(rect.top, 0);
    navigationLoader = element('div', {
        className: 'navigation-loader',
        attributes: {role: 'status', 'aria-live': 'polite'},
        children: [
            element('span', {className: 'navigation-loader-spinner', attributes: {'aria-hidden': 'true'}}),
            element('span', {text: 'Wird geladen …'}),
        ],
    });
    Object.assign(navigationLoader.style, {
        top: `${top}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        height: `${Math.min(rect.bottom, window.innerHeight) - top}px`,
    });
    document.body.append(navigationLoader);
    target.setAttribute('aria-busy', 'true');
};
const whileNavigating = async (task) => {
    pendingNavigations += 1;
    if (pendingNavigations === 1) navigationLoaderTimer = window.setTimeout(showNavigationLoader, NAVIGATION_LOADER_DELAY_MS);
    try {
        return await task();
    } finally {
        pendingNavigations -= 1;
        if (pendingNavigations === 0) {
            window.clearTimeout(navigationLoaderTimer);
            navigationLoaderTimer = null;
            navigationLoader?.remove();
            navigationLoader = null;
            document.querySelector('.admin-workspace')?.removeAttribute('aria-busy');
        }
    }
};

/**
 * Springt zu einem Eintrag aus einem Link in einer Benachrichtigungsmail (z. B.
 * `/admin/kontakt-feedback/gaestebuch/{id}`): sucht in `root` das Element mit passendem
 * `data-link-target`, klappt umgebende `<details>` auf, scrollt hin und hebt es hervor.
 */
const focusLinkedItem = (root, id, notFoundMessage) => {
    const target = [...root.querySelectorAll('[data-link-target]')].find((candidate) => candidate.dataset.linkTarget === id);
    if (!target) {
        toast(notFoundMessage, 'info');
        return;
    }
    for (let details = target.closest('details'); details; details = details.parentElement?.closest('details')) details.open = true;
    target.classList.add('is-link-target');
    target.scrollIntoView({block: 'start'});
};

export {sectionHeading, emptyState, actionMenu, openContextMenu, openContextMenuAtElement, openContextMenuFromEvent, searchField, treeLayout, whileNavigating, actionButton, focusLinkedItem};
