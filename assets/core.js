// Gemeinsame, seitenunabhängige Grundlage für `public.js` und `admin.js`: generische DOM-/Formular-
// Helfer, Benachrichtigungen/Dialoge, der `fetch()`-Wrapper sowie fachdomänen-übergreifende
// Konstanten (Label-Übersetzungen, Formatierung). Kennt weder Sitzungsrollen noch Redaktions-
// spezifisches — das liegt in `admin/session.js` bzw. den jeweiligen `admin/*.js`-Modulen.

const app = document.querySelector('#app');

// Der Access-Token selbst steckt in einem httpOnly-Cookie (siehe `AuthenticationController`) und
// ist damit für JS unsichtbar — nur der CSRF-Token wird clientseitig gehalten, um ihn auf jeder
// nicht-lesenden Anfrage mitzuschicken (Doppel-Submit-Prüfung, siehe `AdminCsrfSubscriber`).
let csrfToken = null;
const setCsrfToken = (value) => { csrfToken = value; };

// `request()` kennt selbst keine Admin-spezifische Logik (Sitzungs-Timer, Login-Seite, …) — bei
// einem 401 innerhalb einer bestehenden Sitzung (csrfToken gesetzt) ruft es stattdessen einen von
// außen registrierten Hook auf. Nur `admin.js` registriert diesen (siehe `setUnauthenticatedHandler`
// dort); auf öffentlichen Seiten bleibt er ungenutzt, da dort nie ein csrfToken gesetzt wird.
let unauthenticatedHandler = null;
const setUnauthenticatedHandler = (handler) => { unauthenticatedHandler = handler; };

const request = async (url, options = {}) => {
    const method = options.method || 'GET';
    const usesFormData = options.body instanceof FormData;
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(options.body && !usesFormData ? {'Content-Type': 'application/json'} : {}),
            ...(!['GET', 'HEAD', 'OPTIONS'].includes(method) && csrfToken ? {'X-CSRF-Token': csrfToken} : {}),
            ...(options.headers || {}),
        },
        ...options,
    });
    const contentType = response.headers.get('content-type') || '';
    const data = response.status === 204 || !contentType.includes('application/json') ? null : await response.json();
    if (!response.ok) {
        const error = new Error(data?.error?.message || data?.detail || data?.message || 'Die Anfrage ist fehlgeschlagen.');
        // Manche Fehler (z. B. „PIN erforderlich“, siehe pin-settings) müssen von Aufrufern
        // unterschieden werden können, statt nur als Text im Toast zu landen.
        error.code = data?.error?.code || null;
        // Nur innerhalb einer bestehenden Sitzung (csrfToken gesetzt) automatisch abmelden — sonst
        // würde ein 401 auf /login-requests, /login oder /me (dort erwartet, siehe admin/auth.js)
        // die gerade angezeigte Anmeldeseite unterbrechen.
        if (response.status === 401 && csrfToken) {
            unauthenticatedHandler?.('Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.');
        }
        throw error;
    }

    return data;
};

// Verhindert, dass die Seite im Hintergrund scrollt, solange ein <dialog> geöffnet ist (nur das
// Overlay selbst soll scrollbar sein) – reagiert automatisch auf jedes showModal()/close(), ohne
// dass jede einzelne Dialogfunktion das selbst verwalten muss.
let dialogScrollLockActive = false;
let dialogScrollLockPosition = 0;
const updateDialogScrollLock = () => {
    const anyDialogOpen = document.querySelector('dialog[open]') !== null;
    if (anyDialogOpen === dialogScrollLockActive) return;
    dialogScrollLockActive = anyDialogOpen;
    if (anyDialogOpen) {
        dialogScrollLockPosition = window.scrollY;
        document.body.style.top = `-${dialogScrollLockPosition}px`;
        document.body.classList.add('dialog-open');
    } else {
        document.body.classList.remove('dialog-open');
        document.body.style.top = '';
        window.scrollTo(0, dialogScrollLockPosition);
    }
};
new MutationObserver(updateDialogScrollLock).observe(document.documentElement, {
    attributes: true, attributeFilter: ['open'], subtree: true,
});
const SALUTATION_LABELS = {mr: 'Herr', ms: 'Frau', diverse: 'Divers'};
const FAMILY_ROLE_LABELS = {none: 'Einzelperson', head: 'Hauptmitglied', partner: 'Familienangehöriger', child: 'Kind'};
const MEMBER_FUNCTION_LABELS = {board: 'Vorstand', member: 'Mitglied', supporter: 'Unterstützer', treasurer: 'Kassenwart'};
const PAYMENT_METHOD_LABELS = {sepa_direct_debit: 'SEPA-Lastschrift', bank_transfer: 'Überweisung', cash: 'Bar', not_specified: 'Keine Angabe'};
const PAYMENT_INTERVAL_LABELS = {
    once: 'Einmalig', yearly: 'Jährlich', half_yearly: 'Halbjährlich', quarterly: 'Quartalsweise', bimonthly: 'Zweimonatlich', monthly: 'Monatlich',
};
const PERSON_GROUP_LABELS = {individual: 'Einzelperson', family: 'Familie'};
const PAYMENT_DAY_LABELS = {first: 'zum 01.', fifteenth: 'zum 15.'};
const PAYER_TYPE_LABELS = {self_payer: 'Selbstzahler', other_member: 'Anderes Mitglied'};
const CONTRIBUTION_CATEGORY_LABELS = {
    individual_junior: 'Einzelperson bis 21 Jahre',
    individual_senior: 'Einzelperson über 21 Jahre',
    family_adult: 'Familie: Elternteil',
    family_child_paying: 'Familie: Kind (zahlend)',
    family_child_exempt: 'Familie: Kind (beitragsfrei)',
    work_assignment_surcharge: 'Arbeitseinsatz-Zuschlag',
};
const formatEuro = (cents) => cents === null || cents === undefined
    ? '–'
    : (cents / 100).toLocaleString('de-DE', {style: 'currency', currency: 'EUR'});
const selectField = (label, name, options, selected = '') => {
    const select = element('select', {
        attributes: {name, id: name},
        children: options.map(([value, text]) => element('option', {text, attributes: {value}})),
    });
    select.value = selected;

    return element('label', {className: 'field', children: [element('span', {text: label}), select]});
};
const radioGroup = (name, legend, options, selected) => {
    const inputs = options.map(([value, text]) => {
        const input = element('input', {attributes: {type: 'radio', name, value}});
        input.checked = value === selected;

        return element('label', {className: 'radio-field', children: [input, element('span', {text})]});
    });

    return element('fieldset', {className: 'radio-group', children: [element('legend', {text: legend}), ...inputs]});
};
const buildPageTree = (pages, includeOrphans = true) => {
    const nodes = new Map(pages.map((page) => [page.id, {...page, children: []}]));
    const roots = [];
    nodes.forEach((node) => {
        const parent = node.parentId ? nodes.get(node.parentId) : null;
        if (parent && parent.id !== node.id) parent.children.push(node);
        else if (!node.parentId || includeOrphans) roots.push(node);
    });
    const sortNodes = (items) => {
        items.sort((left, right) => (left.navigationPosition ?? 0) - (right.navigationPosition ?? 0));
        items.forEach((item) => sortNodes(item.children));
    };
    sortNodes(roots);

    return roots;
};

const flattenPageTree = (nodes, depth = 0) => nodes.flatMap((node) => [
    {page: node, depth},
    ...flattenPageTree(node.children, depth + 1),
]);
const encodePageSlug = (slug) => slug.split('/').map((segment) => encodeURIComponent(segment)).join('/');
const pageHref = (slug) => slug === 'startseite' ? '/' : '/seite/' + encodePageSlug(slug);
const treeContainsSlug = (page, slug) => page.slug === slug || page.children.some((child) => treeContainsSlug(child, slug));
const element = (tag, options = {}) => {
    const node = document.createElement(tag);
    if (options.className) node.className = options.className;
    if (options.text !== undefined) node.textContent = options.text;
    Object.entries(options.attributes || {}).forEach(([name, value]) => {
        if (value !== null && value !== undefined) node.setAttribute(name, String(value));
    });
    if (options.children) node.append(...options.children);

    return node;
};
const toast = (message, type = 'success', duration = 4500) => {
    let region = document.querySelector('.toast-region');
    if (!region) {
        region = element('div', {
            className: 'toast-region',
            attributes: {'aria-label': 'Benachrichtigungen', 'aria-live': 'polite'},
        });
        document.body.append(region);
    }
    const item = element('div', {
        className: `toast toast-${type}`,
        attributes: {role: type === 'error' ? 'alert' : 'status'},
    });
    const close = element('button', {
        className: 'toast-close',
        text: '×',
        attributes: {type: 'button', title: 'Benachrichtigung schließen', 'aria-label': 'Benachrichtigung schließen'},
    });
    const remove = () => {
        item.classList.add('is-leaving');
        window.setTimeout(() => {
            item.remove();
            if (!region.children.length) region.remove();
        }, 180);
    };
    close.addEventListener('click', remove);
    item.append(element('span', {className: 'toast-icon', text: type === 'error' ? '!' : type === 'info' ? 'i' : '✓'}), element('p', {text: message}), close);
    region.append(item);
    window.setTimeout(remove, duration);
};
const confirmAction = (title, description, confirmLabel = 'Entfernen') => new Promise((resolve) => {
    const dialog = element('dialog', {className: 'confirm-dialog'});
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    const confirm = element('button', {className: 'button danger-button', text: confirmLabel, attributes: {type: 'button'}});
    let answered = false;
    const finish = (result) => {
        answered = true;
        resolve(result);
        dialog.close();
    };
    cancel.addEventListener('click', () => finish(false));
    confirm.addEventListener('click', () => finish(true));
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        finish(false);
    });
    dialog.addEventListener('close', () => {
        if (!answered) resolve(false);
        dialog.remove();
    });
    dialog.append(element('div', {className: 'confirm-dialog-content', children: [
        element('p', {className: 'eyebrow', text: 'Bitte bestätigen'}),
        element('h2', {text: title}),
        element('p', {text: description}),
        element('div', {className: 'confirm-dialog-actions', children: [cancel, confirm]}),
    ]}));
    document.body.append(dialog);
    dialog.showModal();
    cancel.focus();
});
const formMessage = () => element('p', {className: 'form-message', attributes: {'aria-live': 'polite'}});
const renderError = (message) => {
    app.replaceChildren(element('main', {
        className: 'error-state',
        children: [
            element('p', {className: 'eyebrow', text: 'Waldbad Borkheide'}),
            element('h1', {text: 'Das hat leider nicht geklappt'}),
            element('p', {text: message}),
            element('a', {className: 'button', text: 'Zur Startseite', attributes: {href: '/'}}),
        ],
    }));
};
const formatDateDE = (isoDate) => isoDate ? new Date(isoDate + 'T00:00:00').toLocaleDateString('de-DE') : null;
const field = (label, name, value = '', type = 'text') => {
    const input = element(type === 'textarea' ? 'textarea' : 'input', {
        attributes: {name, id: name, ...(type !== 'textarea' ? {type} : {})},
    });
    input.value = value ?? '';

    return element('label', {className: 'field', children: [element('span', {text: label}), input]});
};

// Stellt mehrere Felder in einer Zeile nebeneinander dar (z. B. Mitgliedsnummer/Hauptnummer);
// auf schmalen Bildschirmen fällt die Zeile per CSS-Media-Query auf eine Spalte zurück, wie es
// beim einspaltigen Layout ohnehin schon der Fall war. Standardmäßig zwei feste Spalten (auch bei
// nur einem Feld, das dann nur die halbe Breite einnimmt); `columns: 3` für Dreier-Zeilen.
const fieldRow = (fields, columns = 2) => element('div', {className: `field-row field-row-${columns}`, children: fields});

export {
    app,
    csrfToken,
    setCsrfToken,
    setUnauthenticatedHandler,
    request,
    SALUTATION_LABELS,
    FAMILY_ROLE_LABELS,
    MEMBER_FUNCTION_LABELS,
    PAYMENT_METHOD_LABELS,
    PAYMENT_INTERVAL_LABELS,
    PERSON_GROUP_LABELS,
    PAYMENT_DAY_LABELS,
    PAYER_TYPE_LABELS,
    CONTRIBUTION_CATEGORY_LABELS,
    formatEuro,
    selectField,
    radioGroup,
    buildPageTree,
    flattenPageTree,
    encodePageSlug,
    pageHref,
    treeContainsSlug,
    element,
    toast,
    confirmAction,
    formMessage,
    renderError,
    formatDateDE,
    field,
    fieldRow,
};
