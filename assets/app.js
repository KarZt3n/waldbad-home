import './styles/app.css';

const app = document.querySelector('#app');

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

let csrfToken = null;
let currentRoles = [];
let currentModuleAccess = {};
let currentPageAccess = null;

const CMS_MODULES = [
    ['pages', 'Seiten'],
    ['events', 'Veranstaltungen'],
    ['activities', 'Aktivitäten'],
    ['guestbook', 'Gästebuch'],
    ['contact_requests', 'Kontaktanfragen'],
    ['event_helpers', 'Veranstaltungshelfer'],
    ['membership_applications', 'Mitgliedsanträge'],
    ['members', 'Mitglieder'],
    ['contribution_rates', 'Beitragssätze'],
    ['user_management', 'Benutzerverwaltung'],
    ['member_messages', 'Mitgliedernachrichten'],
];

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

const EVENT_SCHEDULE_KIND_LABELS = {event: 'Veranstaltung', work_assignment: 'Arbeitseinsatz'};

const BLOCK_TYPES = {
    heading: 'Überschrift',
    rich_text: 'Text',
    image: 'Bild',
    image_text: 'Bild + Text',
    feature_collection: 'Collection: Bild + Text',
    alert: 'Hinweis',
    call_to_action: 'Handlungsaufruf',
    custom_html: 'Eigenes HTML',
    embedded_page: 'Seite einbetten',
    page_teaser: 'Seitenteaser',
    event: 'Veranstaltung',
    event_reference: 'Veranstaltung einbetten',
    extension: 'Erweiterung',
};

const createCollectionItem = (title = '') => ({
    title,
    content: '',
    mediaUrl: null,
    mediaAlt: null,
    mediaSource: null,
});

const createBlock = (type) => ({
    type,
    content: '',
    mediaUrl: null,
    mediaAlt: null,
    mediaSource: null,
    linkUrl: null,
    linkLabel: null,
    layout: ['image_text', 'page_teaser', 'event_reference'].includes(type) ? 'image_left' : (type === 'image' ? 'center' : null),
    imageWidthPercent: ['image_text', 'page_teaser', 'event_reference'].includes(type) ? 50 : (type === 'image' ? 100 : null),
    verticalAlignment: ['image_text', 'page_teaser', 'event_reference'].includes(type) ? 'center' : null,
    textAlignment: ['image_text', 'page_teaser', 'event_reference'].includes(type) ? 'left' : null,
    imageFit: ['image_text', 'page_teaser', 'event_reference'].includes(type) ? 'cover' : null,
    embeddedPageId: null,
    eventTitle: type === 'event' ? '' : null,
    eventDate: type === 'event' ? '' : null,
    eventTime: type === 'event' ? '14:00' : null,
    eventIdentifier: type === 'event' ? crypto.randomUUID() : null,
    eventHelpEnabled: type === 'event',
    eventHelpButtonLabel: type === 'event' ? 'Ich möchte helfen!' : null,
    eventActivities: [],
    eventCallToActions: [],
    extensionKey: type === 'extension' ? 'membership_application' : null,
    collectionColumns: type === 'feature_collection' ? 3 : null,
    collectionItems: [],
});

const hasAnyRole = (...roles) => currentRoles.some((role) => roles.includes(role));
const isGlobalAdministrator = () => hasAnyRole('admin', 'super_admin');
const hasModule = (module) => typeof currentModuleAccess[module] === 'string';
const moduleRole = (module) => currentModuleAccess[module] || null;
const canEditModule = (module) => hasModule(module)
    && (isGlobalAdministrator() || moduleRole(module) === 'editor');
const canViewPages = () => hasModule('pages');
const isPageAccessRestricted = () => currentPageAccess !== null && !isGlobalAdministrator();
const pageRole = (pageId) => pageId && currentPageAccess ? currentPageAccess[pageId] || null : null;
const canEditPages = (pageId = null) => canViewPages() && (
    isGlobalAdministrator()
    || (isPageAccessRestricted()
        ? ['editor', 'publisher'].includes(pageRole(pageId))
        : ['editor', 'publisher', 'moderator'].includes(moduleRole('pages')))
);
const canPublishPages = (pageId = null) => canViewPages() && (
    isGlobalAdministrator()
    || (isPageAccessRestricted() ? pageRole(pageId) === 'publisher' : moduleRole('pages') === 'publisher')
);
const canManagePageStructure = () => canEditPages() && !isPageAccessRestricted();

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

/**
 * Buckets items with a `YYYY-MM-DD` date (and `HH:MM` time) into „Heute“, „Kommende“,
 * „Abgeschlossen (aktuelles Jahr)“ and an archive grouped by year — the grouping used by the
 * „Veranstaltungshelfer“ and „Veranstaltungen“ admin modules.
 */
const bucketItemsByDate = (items, getDate, getTime) => {
    const now = new Date();
    const currentYear = now.getFullYear();
    const today = [currentYear, now.getMonth() + 1, now.getDate()]
        .map((part, index) => index === 0 ? String(part) : String(part).padStart(2, '0'))
        .join('-');
    const compareAscending = (first, second) => {
        const dateComparison = getDate(first).localeCompare(getDate(second));

        return dateComparison !== 0 ? dateComparison : getTime(first).localeCompare(getTime(second));
    };
    const compareDescending = (first, second) => compareAscending(second, first);
    const todayItems = items.filter((item) => getDate(item) === today).sort(compareAscending);
    const upcomingItems = items.filter((item) => getDate(item) > today).sort(compareAscending);
    const completedCurrentYearItems = items
        .filter((item) => getDate(item) < today && Number.parseInt(getDate(item).slice(0, 4), 10) === currentYear)
        .sort(compareDescending);
    const archiveByYear = items
        .filter((item) => getDate(item) < today && Number.parseInt(getDate(item).slice(0, 4), 10) < currentYear)
        .reduce((years, item) => {
            const year = Number.parseInt(getDate(item).slice(0, 4), 10);
            if (!years.has(year)) years.set(year, []);
            years.get(year).push(item);

            return years;
        }, new Map());
    archiveByYear.forEach((yearItems) => yearItems.sort(compareDescending));

    return {currentYear, todayItems, upcomingItems, completedCurrentYearItems, archiveByYear};
};

const encodePageSlug = (slug) => slug.split('/').map((segment) => encodeURIComponent(segment)).join('/');
const pageHref = (slug) => slug === 'startseite' ? '/' : '/seite/' + encodePageSlug(slug);
const treeContainsSlug = (page, slug) => page.slug === slug || page.children.some((child) => treeContainsSlug(child, slug));
const slugify = (value) => value
    .trim()
    .toLocaleLowerCase('de-DE')
    .replaceAll('ä', 'ae')
    .replaceAll('ö', 'oe')
    .replaceAll('ü', 'ue')
    .replaceAll('ß', 'ss')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
const hierarchicalSlug = (title, parentId, pages) => {
    const leafSlug = slugify(title);
    if (!leafSlug) return '';

    const parent = pages.find((candidate) => candidate.id === parentId);
    return parent ? `${parent.slug}/${leafSlug}` : leafSlug;
};

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

// Fragt einen PIN ab (siehe Modul „Einstellungen“ → PIN-Schutz). `verify(pin)` führt die
// eigentliche, serverseitig zu prüfende Aktion aus (z. B. den Verify-Endpunkt oder direkt die zu
// schützende Aktion selbst, siehe `deleteMemberWithOptionalPin`) — schlägt sie fehl, bleibt der
// Dialog offen und zeigt die Fehlermeldung, statt (wie bei `confirmAction`) einfach neu geöffnet
// werden zu müssen. Löst mit `true` auf, sobald `verify` einmal erfolgreich war, oder mit `false`
// bei „Abbrechen“.
const promptForPin = (title, description, verify) => new Promise((resolve) => {
    const dialog = element('dialog', {className: 'confirm-dialog'});
    const pinField = field('PIN', 'pin-prompt-input', '', 'password');
    const pinInput = pinField.querySelector('input');
    pinInput.inputMode = 'numeric';
    pinInput.autocomplete = 'off';
    pinInput.maxLength = 8;
    const message = formMessage();
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    const confirm = element('button', {className: 'button', text: 'Bestätigen', attributes: {type: 'submit'}});
    let answered = false;
    const finish = (result) => {
        answered = true;
        resolve(result);
        dialog.close();
    };
    const form = element('form', {className: 'confirm-dialog-content', children: [
        element('p', {className: 'eyebrow', text: 'PIN erforderlich'}),
        element('h2', {text: title}),
        element('p', {text: description}),
        pinField,
        message,
        element('div', {className: 'confirm-dialog-actions', children: [cancel, confirm]}),
    ]});
    cancel.addEventListener('click', () => finish(false));
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        finish(false);
    });
    dialog.addEventListener('close', () => {
        if (!answered) resolve(false);
        dialog.remove();
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const pin = pinInput.value.trim();
        if (!pin) return;
        confirm.disabled = true;
        try {
            await verify(pin);
            finish(true);
        } catch (error) {
            message.textContent = error.message;
            pinInput.value = '';
            pinInput.focus();
        } finally {
            confirm.disabled = false;
        }
    });
    dialog.append(form);
    document.body.append(dialog);
    dialog.showModal();
    pinInput.focus();
});

// „Entsperrt“ ein per PIN geschütztes Modul (siehe `ProtectedAction`) für den Rest der
// Browser-Sitzung, statt bei jedem erneuten Öffnen desselben Bereichs erneut nach dem PIN zu
// fragen — anders als bei einzelnen, folgenschweren Aktionen (z. B. „Mitglied löschen“, siehe
// `deleteMemberWithOptionalPin`), wo bewusst jedes Mal neu geprüft wird. sessionStorage-Zugriffe
// sind bewusst abgesichert: in manchen Browserkontexten (z. B. privates Fenster) kann der Zugriff
// werfen, das darf die eigentliche Freischaltung nicht verhindern.
const isPinSessionUnlocked = (actionKey) => {
    try { return sessionStorage.getItem(`pin-unlocked:${actionKey}`) === 'true'; } catch { return false; }
};
const markPinSessionUnlocked = (actionKey) => {
    try { sessionStorage.setItem(`pin-unlocked:${actionKey}`, 'true'); } catch { /* siehe oben */ }
};
// Beim Abmelden aufgerufen: Ein „Entsperrt“-Status darf nicht in eine andere Sitzung im selben
// Browser-Tab übernommen werden (z. B. wenn sich danach ein anderer Benutzer anmeldet).
const clearPinSessionUnlocks = () => {
    try {
        Object.keys(sessionStorage)
            .filter((key) => key.startsWith('pin-unlocked:'))
            .forEach((key) => sessionStorage.removeItem(key));
    } catch { /* siehe oben */ }
};

/**
 * Prüft, ob $actionKey aktuell per PIN geschützt ist, und fragt den PIN bei Bedarf ab. Liefert
 * `true`, wenn die aufrufende Stelle fortfahren darf (kein Schutz aktiv, bereits in dieser Sitzung
 * entsperrt, oder gerade erfolgreich per PIN entsperrt) — `false` bei „Abbrechen“.
 */
const ensurePinUnlocked = async (actionKey, title) => {
    if (isPinSessionUnlocked(actionKey)) return true;
    let settings;
    try {
        settings = await request('/api/admin/v1/pin-settings');
    } catch {
        // Einstellungen nicht abrufbar (z. B. Netzwerkfehler) — nicht blockieren, die eigentliche
        // Aktion greift ohnehin nur, wenn die reguläre Berechtigung dafür vorliegt.
        return true;
    }
    if (!settings.protectedActions.includes(actionKey)) return true;

    const unlocked = await promptForPin(title, 'Für diesen Bereich ist zusätzlich ein PIN erforderlich.', (pin) => request(
        '/api/admin/v1/pin-settings/verify',
        {method: 'POST', body: JSON.stringify({action: actionKey, pin})},
    ));
    if (unlocked) markPinSessionUnlocked(actionKey);

    return unlocked;
};

/**
 * Löscht ein Mitglied und berücksichtigt dabei einen möglichen PIN-Schutz für „Mitglied löschen“
 * (`members.delete`) — anders als bei `ensurePinUnlocked` bewusst ohne vorherigen, gesonderten
 * Prüf-Aufruf und ohne sitzungsweites Merken: Der erste Versuch läuft ohne PIN; ist die Aktion
 * gerade geschützt, meldet der Server das über `error.code === 'pinrequiredexception'` und genau
 * dann wird der PIN abgefragt — jedes Mal neu, da es sich um eine einzelne, endgültige Aktion
 * handelt. Liefert `true` bei Erfolg (direkt oder nach PIN-Eingabe), `false` bei „Abbrechen“.
 */
const deleteMemberWithOptionalPin = async (id) => {
    try {
        await request(`/api/admin/v1/members/${id}`, {method: 'DELETE'});

        return true;
    } catch (error) {
        if (error.code !== 'pinrequiredexception') throw error;
    }

    return promptForPin(
        'Mitglied löschen',
        'Für diese Funktion ist zusätzlich ein PIN erforderlich.',
        (pin) => request(`/api/admin/v1/members/${id}`, {method: 'DELETE', body: JSON.stringify({pin})}),
    );
};

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
        throw error;
    }

    return data;
};

const formMessage = () => element('p', {className: 'form-message', attributes: {'aria-live': 'polite'}});

const renderContactForm = () => {
    const message = formMessage();
    const privacy = element('input', {attributes: {name: 'privacyAccepted', type: 'checkbox', required: 'required'}});
    const form = element('form', {className: 'public-form', children: [
        element('h2', {text: 'Nachricht senden'}),
        element('div', {className: 'form-grid', children: [field('Name', 'name'), field('E-Mail-Adresse', 'email', '', 'email')]}),
        field('Betreff (optional)', 'subject'),
        field('Nachricht', 'message', '', 'textarea'),
        element('label', {className: 'check-field', children: [privacy, element('span', {text: 'Ich stimme der Verarbeitung meiner Angaben zur Beantwortung der Anfrage zu.'})]}),
        message,
        element('button', {className: 'button', text: 'Nachricht senden', attributes: {type: 'submit'}}),
    ]});
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        try {
            const result = await request('/api/public/v1/contact-requests', {method: 'POST', body: JSON.stringify({
                name: data.get('name'), email: data.get('email'), subject: data.get('subject'),
                message: data.get('message'), privacyAccepted: data.get('privacyAccepted') === 'on',
            })});
            form.reset();
            message.textContent = result.message;
            message.classList.add('success');
            toast(result.message);
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
    });
    return form;
};

const renderGuestbook = async () => {
    const pageSize = 10;
    const message = formMessage();
    const form = element('form', {className: 'public-form', children: [
        element('h2', {text: 'Ins Gästebuch schreiben'}),
        element('div', {className: 'form-grid', children: [field('Anzeigename', 'displayName'), field('E-Mail (wird nicht veröffentlicht)', 'email', '', 'email')]}),
        field('Nachricht', 'message', '', 'textarea'),
        message,
        element('button', {className: 'button', text: 'Eintrag absenden', attributes: {type: 'submit'}}),
    ]});
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        try {
            const response = await request('/api/public/v1/guestbook-entries', {method: 'POST', body: JSON.stringify({
                displayName: data.get('displayName'), email: data.get('email'), message: data.get('message'),
            })});
            form.reset();
            message.textContent = response.message;
            message.classList.add('success');
            toast(response.message);
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
    });

    const heading = element('h2', {text: 'Gästebucheinträge'});
    const list = element('div', {className: 'guestbook-list'});
    const pagination = element('nav', {className: 'guestbook-pagination', attributes: {'aria-label': 'Gästebuch-Seiten'}});

    const loadPage = async (requestedPage) => {
        list.replaceChildren(element('p', {className: 'empty-copy', text: 'Einträge werden geladen …'}));
        pagination.replaceChildren();

        try {
            const offset = (requestedPage - 1) * pageSize;
            const result = await request(`/api/public/v1/guestbook-entries?limit=${pageSize}&offset=${offset}`);
            const totalPages = Math.max(1, Math.ceil(result.total / pageSize));
            const currentPage = Math.min(requestedPage, totalPages);

            list.replaceChildren(...(result.items.length
                ? result.items.map((entry) => element('article', {className: 'guestbook-entry', children: [
                    element('p', {text: entry.message}),
                    element('footer', {text: entry.displayName + ' · ' + new Date(entry.submittedAt).toLocaleString('de-DE', {
                        dateStyle: 'medium', timeStyle: 'short',
                    })}),
                ]}))
                : [element('p', {className: 'empty-copy', text: 'Noch gibt es keine freigegebenen Einträge.'})]));

            if (totalPages <= 1) return;

            const pageButton = (label, page, options = {}) => {
                const button = element('button', {
                    className: options.current ? 'pagination-button is-current' : 'pagination-button',
                    text: label,
                    attributes: {
                        type: 'button',
                        'aria-label': options.ariaLabel || `Seite ${page}`,
                        ...(options.current ? {'aria-current': 'page'} : {}),
                        ...(options.disabled ? {disabled: 'disabled'} : {}),
                    },
                });
                if (!options.disabled && !options.current) {
                    button.addEventListener('click', async () => {
                        await loadPage(page);
                        heading.scrollIntoView({behavior: 'smooth', block: 'start'});
                    });
                }
                return button;
            };

            pagination.replaceChildren(
                pageButton('← Zurück', currentPage - 1, {disabled: currentPage === 1, ariaLabel: 'Vorherige Seite'}),
                ...Array.from({length: totalPages}, (_, index) => {
                    const page = index + 1;
                    return pageButton(String(page), page, {current: page === currentPage});
                }),
                pageButton('Weiter →', currentPage + 1, {disabled: currentPage === totalPages, ariaLabel: 'Nächste Seite'}),
            );
        } catch (error) {
            list.replaceChildren(element('p', {className: 'form-message error', text: error.message}));
        }
    };

    await loadPage(1);

    return element('section', {className: 'interactive-section guestbook-section', children: [
        form,
        heading,
        list,
        pagination,
    ]});
};

const renderMembershipApplicationForm = (preview = false) => {
    const instanceId = `membership-${Math.random().toString(36).slice(2)}`;
    const message = formMessage();
    const applicants = element('div', {className: 'membership-applicants'});
    const addPerson = element('button', {className: 'secondary-button', text: '＋ Weitere Person', attributes: {type: 'button'}});

    const applicantField = (label, key, type = 'text', required = true) => {
        const wrapper = field(label, `${instanceId}-${key}-${applicants.children.length}`, '', type);
        const input = wrapper.querySelector('input');
        input.dataset.applicantField = key;
        if (required) input.required = true;
        return wrapper;
    };
    const applicantSalutationField = () => {
        const wrapper = selectField('Anrede', `${instanceId}-salutation-${applicants.children.length}`, [
            ['', 'Bitte wählen'],
            ...Object.entries(SALUTATION_LABELS),
        ], '');
        const select = wrapper.querySelector('select');
        select.dataset.applicantField = 'salutation';
        select.required = true;
        wrapper.classList.add('membership-person-salutation');
        return wrapper;
    };
    const refreshApplicantCards = () => {
        [...applicants.children].forEach((card, index) => {
            card.querySelector('.membership-person-title').textContent = `Person ${index + 1}`;
            const email = card.querySelector('[data-applicant-field="email"]');
            email.required = index === 0;
            email.closest('.field').querySelector('span').textContent = index === 0 ? 'E-Mail-Adresse' : 'E-Mail-Adresse (optional)';
            const remove = card.querySelector('.membership-remove-person');
            remove.hidden = index === 0 && applicants.children.length === 1;
        });
        addPerson.disabled = applicants.children.length >= 8;
    };
    const appendApplicant = () => {
        if (applicants.children.length >= 8) return;
        const isFirstPerson = applicants.children.length === 0;
        const remove = element('button', {className: 'text-button danger membership-remove-person', text: 'Person entfernen', attributes: {type: 'button'}});
        const emailField = applicantField('E-Mail-Adresse (optional)', 'email', 'email', false);
        // Für jede weitere Person ist die E-Mail-Adresse optional (nur Person 1 braucht zwingend
        // eine, siehe refreshApplicantCards) — als Vorschlag wird die von Person 1 übernommen,
        // damit nicht jede Familienangehörige einzeln dieselbe Adresse eintragen muss. Bleibt
        // änderbar, falls jemand eine eigene Adresse hat.
        if (!isFirstPerson) {
            const firstEmailInput = applicants.children[0]?.querySelector('[data-applicant-field="email"]');
            const firstEmail = firstEmailInput?.value.trim();
            if (firstEmail) emailField.querySelector('input').value = firstEmail;
        }
        const card = element('fieldset', {className: 'membership-person', children: [
            element('div', {className: 'membership-person-heading', children: [
                element('legend', {className: 'membership-person-title', text: 'Person'}),
                remove,
            ]}),
            element('div', {className: 'form-grid', children: [
                applicantSalutationField(),
                applicantField('Vorname', 'firstName'),
                applicantField('Nachname', 'lastName'),
                applicantField('Geburtsdatum', 'birthDate', 'date'),
                applicantField('Telefon (optional)', 'phone', 'tel', false),
                applicantField('Straße', 'street'),
                applicantField('Hausnummer', 'houseNumber'),
                applicantField('Postleitzahl', 'postalCode'),
                applicantField('Wohnort', 'city'),
                emailField,
            ]}),
        ]});
        remove.addEventListener('click', async () => {
            const confirmed = await confirmAction('Person entfernen?', 'Die eingegebenen Daten dieser Person werden aus dem Antrag entfernt.', 'Person entfernen');
            if (!confirmed) return;
            card.remove();
            refreshApplicantCards();
            toast('Person wurde aus dem Antrag entfernt.', 'info');
        });
        applicants.append(card);
        refreshApplicantCards();
    };

    appendApplicant();
    addPerson.addEventListener('click', appendApplicant);

    const consent = (name, text, required = true) => {
        const input = element('input', {attributes: {name, type: 'checkbox', ...(required ? {required: 'required'} : {})}});
        return element('label', {className: 'check-field', children: [input, element('span', {text})]});
    };
    const form = element('form', {className: 'public-form membership-form', children: [
        element('header', {className: 'membership-intro', children: [
            element('p', {className: 'eyebrow', text: 'Naturbad Borkheide e.V.'}),
            element('h2', {text: 'Beitrittserklärung'}),
            element('p', {text: 'Fülle den Antrag für dich oder deine Familie aus. Weitere Familienmitglieder können direkt ergänzt werden.'}),
        ]}),
        applicants,
        addPerson,
        element('section', {className: 'membership-section', children: [
            element('h3', {text: 'SEPA-Einzugsermächtigung'}),
            element('div', {className: 'form-grid', children: [
                field('Kontoinhaber', 'accountHolder'),
                field('IBAN', 'iban'),
                field('Bank / Ort (optional)', 'bankName'),
            ]}),
        ]}),
        element('section', {className: 'membership-section membership-consents', children: [
            element('h3', {text: 'Bestätigungen'}),
            consent('termsAccepted', 'Ich erkenne die Vereinssatzung und die Beitragsordnung an.'),
            consent('privacyAccepted', 'Ich stimme der Verarbeitung meiner Angaben zur Mitgliederverwaltung gemäß Datenschutzerklärung zu.'),
            consent('sepaAccepted', 'Ich ermächtige den Naturbad Borkheide e.V. widerruflich, die Mitgliedsbeiträge per Lastschrift einzuziehen.'),
            consent('emailConsent', 'Ich möchte Informationen des Vereins per E-Mail erhalten.', false),
            field('Name der unterzeichnenden Person', 'signerName'),
            element('small', {text: 'Mit dem Absenden bestätigst du die Richtigkeit deiner Angaben. Bei Minderjährigen ist der Name der gesetzlichen Vertretung einzutragen.'}),
        ]}),
        message,
        element('button', {className: 'button membership-submit', text: preview ? 'In der Vorschau nicht absendbar' : 'Mitgliedsantrag verbindlich absenden', attributes: {type: 'submit', ...(preview ? {disabled: 'disabled'} : {})}}),
    ]});
    form.querySelector('[name="accountHolder"]').required = true;
    form.querySelector('[name="iban"]').required = true;
    form.querySelector('[name="signerName"]').required = true;
    form.querySelector('[name="iban"]').setAttribute('autocomplete', 'off');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (preview) return;
        const data = new FormData(form);
        const applicantPayload = [...applicants.children].map((card) => Object.fromEntries(
            [...card.querySelectorAll('[data-applicant-field]')].map((input) => [input.dataset.applicantField, input.value.trim()]),
        ));
        const submit = form.querySelector('.membership-submit');
        submit.disabled = true;
        message.textContent = 'Der Antrag wird sicher übermittelt …';
        message.classList.remove('success');
        try {
            const response = await request('/api/public/v1/membership-applications', {method: 'POST', body: JSON.stringify({
                applicants: applicantPayload,
                accountHolder: data.get('accountHolder'),
                iban: data.get('iban'),
                bankName: data.get('bankName'),
                signerName: data.get('signerName'),
                termsAccepted: data.get('termsAccepted') === 'on',
                privacyAccepted: data.get('privacyAccepted') === 'on',
                sepaAccepted: data.get('sepaAccepted') === 'on',
                emailConsent: data.get('emailConsent') === 'on',
            })});
            const success = element('section', {className: 'membership-success', attributes: {role: 'status'}, children: [
                element('p', {className: 'eyebrow', text: 'Antrag eingegangen'}),
                element('h2', {text: 'Vielen Dank für deinen Beitrittswunsch'}),
                element('p', {text: response.message}),
                element('small', {text: `Vorgangsnummer: ${response.id}`}),
            ]});
            form.replaceWith(success);
            toast(response.message);
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
            submit.disabled = false;
        }
    });

    return element('section', {className: 'membership-extension', children: [form]});
};

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

const openEventHelpDialog = async (block) => {
    const availability = await request(`/api/public/v1/event-activities/${encodeURIComponent(block.eventIdentifier)}`);
    const dialog = element('dialog', {className: 'event-help-dialog'});
    const message = formMessage();
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Helferanmeldung schließen'}});
    const privacy = element('input', {attributes: {name: 'privacyAccepted', type: 'checkbox', required: 'required'}});
    const isMember = element('input', {attributes: {name: 'isMember', type: 'checkbox', checked: 'checked'}});
    const activityChoices = (availability.items || []).map((activity) => {
        const isFull = activity.registeredHelpers >= activity.requiredHelpers;
        const input = element('input', {attributes: {
            type: 'checkbox', name: 'activityIds', value: activity.id,
            ...(isFull ? {disabled: 'disabled'} : {}),
        }});
        const scheduleParts = [];
        if (activity.time) scheduleParts.push(`Start: ${activity.time}`);
        if (activity.meetTime) scheduleParts.push(`Ende: ${activity.meetTime}`);
        if (activity.meetPlace) scheduleParts.push(`Treffpunkt: ${activity.meetPlace}`);
        return element('label', {className: `event-activity-choice${isFull ? ' is-full' : ''}`, children: [
            input,
            element('span', {children: [
                element('strong', {text: activity.name}),
                element('small', {text: isFull
                    ? `Belegt · ${activity.registeredHelpers} von ${activity.requiredHelpers} Helfern angemeldet`
                    : `${activity.registeredHelpers} von ${activity.requiredHelpers} Helfern angemeldet`}),
                ...(activity.description ? [element('small', {text: activity.description})] : []),
                ...(scheduleParts.length ? [element('small', {className: 'event-activity-schedule', text: scheduleParts.join(' · ')})] : []),
                ...(activity.remark ? [element('small', {className: 'event-activity-remark', text: activity.remark})] : []),
            ]}),
        ]});
    });
    const activityInputs = activityChoices.map((choice) => choice.querySelector('input'));
    const selectableActivityInputs = activityInputs.filter((input) => !input.disabled);
    const updateActivityRequirement = () => {
        const hasSelection = selectableActivityInputs.some((input) => input.checked);
        selectableActivityInputs.forEach((input, index) => input.required = !hasSelection && index === 0);
    };
    selectableActivityInputs.forEach((input) => input.addEventListener('change', updateActivityRequirement));
    updateActivityRequirement();
    const allActivitiesFull = activityChoices.length > 0 && selectableActivityInputs.length === 0;
    const submitButton = element('button', {
        className: 'button',
        text: allActivitiesFull ? 'Aktuell keine Plätze frei' : 'Helferanmeldung absenden',
        attributes: {type: 'submit', ...(allActivitiesFull ? {disabled: 'disabled'} : {})},
    });
    const form = element('form', {className: 'public-form event-help-form', children: [
        element('header', {children: [
            element('p', {className: 'eyebrow', text: 'Helferanmeldung'}),
            element('h2', {text: block.eventTitle || 'Veranstaltung'}),
            element('p', {text: 'Schön, dass du uns unterstützen möchtest. Teile uns kurz mit, wobei du helfen kannst.'}),
        ]}),
        element('div', {className: 'form-grid', children: [field('Vorname', 'firstName'), field('Nachname', 'lastName')]}),
        element('label', {className: 'check-field', children: [isMember, element('span', {text: 'Ich bin Mitglied'})]}),
        element('div', {className: 'form-grid', children: [
            field('E-Mail (optional)', 'email', '', 'email'),
            field('Geburtsdatum (optional)', 'birthDate', '', 'date'),
        ]}),
        ...(activityChoices.length ? [element('fieldset', {className: 'event-activity-choices', children: [
            element('legend', {text: 'Wobei möchtest du helfen?'}),
            ...activityChoices,
        ]})] : []),
        field('Nachricht / Wobei möchtest du helfen? (optional)', 'message', '', 'textarea'),
        element('label', {className: 'check-field', children: [privacy, element('span', {text: 'Ich stimme der Verarbeitung meiner Angaben zur Organisation dieser Veranstaltung zu.'})]}),
        message,
        submitButton,
    ]});
    form.querySelector('[name="firstName"]').required = true;
    form.querySelector('[name="lastName"]').required = true;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const response = await request('/api/public/v1/event-help-requests', {method: 'POST', body: JSON.stringify({
                eventIdentifier: block.eventIdentifier,
                firstName: data.get('firstName'),
                lastName: data.get('lastName'),
                message: data.get('message'),
                activityIds: data.getAll('activityIds'),
                privacyAccepted: data.get('privacyAccepted') === 'on',
                isMember: data.get('isMember') === 'on',
                email: data.get('email'),
                birthDate: data.get('birthDate'),
            })});
            toast(response.message);
            dialog.close();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
            submit.disabled = allActivitiesFull;
        }
    });
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(close, form);
    document.body.append(dialog);
    dialog.showModal();
};

const renderImageSource = (source) => source
    ? element('figcaption', {className: 'image-source', text: `Bildquelle: ${source}`})
    : null;

const eventScheduleToBlockShape = (item) => ({
    type: 'event',
    content: item.content,
    mediaUrl: item.mediaUrl,
    mediaAlt: item.mediaAlt,
    mediaSource: item.mediaSource,
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

const renderEventScheduleExtension = (kind, mode, context) => {
    const container = element('section', {className: 'event-schedule-extension', attributes: {'aria-live': 'polite'}});
    const emptyMessage = mode === 'next'
        ? (kind === 'any' ? 'Aktuell ist keine weitere Veranstaltung oder Arbeitseinsatz geplant.'
            : kind === 'work_assignment' ? 'Aktuell ist kein weiterer Arbeitseinsatz geplant.' : 'Aktuell ist keine weitere Veranstaltung geplant.')
        : (kind === 'work_assignment' ? 'Aktuell sind keine Arbeitseinsätze für dieses Jahr eingetragen.' : 'Aktuell sind keine Veranstaltungen für dieses Jahr eingetragen.');
    if (context.isPreview) {
        container.append(element('p', {className: 'empty-copy', text: emptyMessage}));
        return container;
    }
    const endpoint = mode === 'next'
        ? `/api/public/v1/events/next?kind=${encodeURIComponent(kind)}`
        : `/api/public/v1/events?kind=${encodeURIComponent(kind)}`;
    request(endpoint).then((data) => {
        const items = mode === 'next' ? (data.item ? [data.item] : []) : (data.items || []);
        if (!items.length) {
            container.replaceChildren(element('p', {className: 'empty-copy', text: emptyMessage}));
            return;
        }
        container.replaceChildren(...items.map((item) => renderPublicBlock(eventScheduleToBlockShape(item), context)));
    }).catch(() => {
        container.replaceChildren(...(context.showEmbedErrors
            ? [element('p', {className: 'embedded-page-error', text: 'Die Veranstaltungen konnten nicht geladen werden.'})]
            : []));
    });

    return container;
};

const renderPublicBlock = (block, context = {visited: new Set(), pagesById: null, showEmbedErrors: false, isPreview: false}) => {
    if (block.type === 'extension' && block.extensionKey === 'membership_application') {
        return renderMembershipApplicationForm(context.isPreview === true);
    }
    if (block.type === 'extension' && ['events_current_year', 'work_assignments_current_year', 'next_event', 'next_work_assignment', 'next_event_or_work_assignment'].includes(block.extensionKey)) {
        const kind = block.extensionKey === 'next_event_or_work_assignment' ? 'any'
            : (block.extensionKey.startsWith('work_assignments') || block.extensionKey === 'next_work_assignment' ? 'work_assignment' : 'event');
        const mode = block.extensionKey.startsWith('next_') ? 'next' : 'current_year';

        return renderEventScheduleExtension(kind, mode, context);
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
                    await openEventHelpDialog(block);
                } catch (error) {
                    toast(error.message, 'error');
                }
            });
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
            attributes: {style: `--event-image-width: ${imageWidth}%`},
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
        return container;
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
        return element('figure', {
            className: `content-image align-${imageAlignment}`,
            attributes: {style: `--image-width: ${imageWidth}%`},
            children: [
                element('img', {attributes: {src: block.mediaUrl, alt: block.mediaAlt || '', loading: 'lazy'}}),
                ...(block.mediaSource ? [renderImageSource(block.mediaSource)] : []),
            ],
        });
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
                element('a', {className: 'button', text: block.linkLabel, attributes: {href: block.linkUrl, target:'_blank'}}),
            ],
        });
    }

    return element('p', {className: 'prose', text: block.content});
};

const renderContentCard = (page, context) => element('article', {
    className: 'content-card',
    children: page.blocks.map((block) => renderPublicBlock(block, context)),
});

const updateDocumentMetadata = (page) => {
    document.title = `${page.seoTitle || page.title} – Waldbad Borkheide`;
    let description = document.querySelector('meta[name="description"]');
    if (!description) {
        description = document.createElement('meta');
        description.setAttribute('name', 'description');
        document.head.append(description);
    }
    description.setAttribute('content', page.seoDescription || 'Natürlich baden ohne Chlor im Waldbad Borkheide.');
};

// Fester Slug der eigenständigen Route `/meine-mitgliedschaft` (siehe `FrontendController`) — keine
// CMS-Seite, die Ansicht hängt nicht von redaktionell gepflegtem Inhalt ab (siehe `renderPublic`,
// `renderMemberSelfServicePage`).
const MEMBER_ACCESS_SLUG = 'meine-mitgliedschaft';

/**
 * Baut die Kopfzeile inkl. Hauptnavigation, verwendet von `renderPublic` sowohl für normale
 * CMS-Seiten als auch für die eigenständige „Meine Mitgliedschaft"-Ansicht — `extraChildren` hängt
 * zusätzliche Elemente rechts neben die Navigation (siehe `buildMemberAccessNav`).
 */
const buildSiteHeader = (navigationTree, activeSlug, extraChildren = []) => {
    const renderNavigationItem = (item, nested = false) => {
        const active = treeContainsSlug(item, activeSlug);
        const link = element('a', {
            className: item.slug === activeSlug ? 'active' : '',
            text: item.label,
            attributes: {
                href: pageHref(item.slug),
                ...(item.slug === activeSlug ? {'aria-current': 'page'} : {}),
            },
        });
        if (!item.children.length) return nested ? link : element('div', {className: 'main-nav-item', children: [link]});

        const toggle = element('button', {
            className: 'submenu-toggle',
            attributes: {type: 'button', 'aria-label': `Unterseiten von ${item.label} anzeigen`, 'aria-expanded': 'false'},
        });
        const container = element('div', {
            className: `main-nav-item has-children${active ? ' active-branch' : ''}`,
            children: [
                link,
                toggle,
                element('div', {className: 'submenu', children: item.children.map((child) => renderNavigationItem(child, true))}),
            ],
        });
        toggle.addEventListener('click', () => {
            const open = container.classList.toggle('submenu-open');
            toggle.setAttribute('aria-expanded', String(open));
        });

        return container;
    };
    const links = navigationTree.map((item) => renderNavigationItem(item));

    const mainNav = element('nav', {
        className: 'main-nav',
        attributes: {id: 'main-nav', 'aria-label': 'Hauptnavigation'},
        children: links,
    });
    const navToggle = element('button', {
        className: 'nav-toggle',
        attributes: {type: 'button', 'aria-controls': 'main-nav', 'aria-expanded': 'false', 'aria-label': 'Menü öffnen'},
        children: [
            element('span', {className: 'nav-toggle-bar'}),
            element('span', {className: 'nav-toggle-bar'}),
            element('span', {className: 'nav-toggle-bar'}),
        ],
    });
    const header = element('header', {
        className: 'site-header',
        children: [
            element('a', {
                className: 'brand',
                attributes: {href: '/', 'aria-label': 'Waldbad Borkheide – Startseite'},
                children: [
                    element('img', {
                        className: 'brand-logo',
                        attributes: {
                            src: '/downloads/waldbad-borkheide-logo.svg',
                            alt: '',
                            width: '96',
                            height: '72',
                            fetchpriority: 'high',
                        },
                    }),
                    element('span', {children: [
                        element('strong', {text: 'Waldbad Borkheide'}),
                        element('small', {text: '… natürlich baden!'}),
                    ]}),
                ],
            }),
            navToggle,
            mainNav,
            ...extraChildren,
        ],
    });
    const closeNav = () => {
        header.classList.remove('nav-open');
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.setAttribute('aria-label', 'Menü öffnen');
    };
    navToggle.addEventListener('click', () => {
        const open = header.classList.toggle('nav-open');
        navToggle.setAttribute('aria-expanded', String(open));
        navToggle.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
    });
    mainNav.addEventListener('click', (event) => {
        if (event.target.closest('a')) closeNav();
    });
    document.addEventListener('click', (event) => {
        if (header.classList.contains('nav-open') && !header.contains(event.target)) closeNav();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && header.classList.contains('nav-open')) closeNav();
    });

    return header;
};

const buildSiteFooter = () => element('footer', {
    className: 'site-footer',
    children: [
        element('p', {text: '© ' + new Date().getFullYear() + ' Naturbad Borkheide e.V.'}),
        element('nav', {attributes: {'aria-label': 'Servicenavigation'}, children: [
            element('a', {text: 'Impressum', attributes: {href: '/seite/impressum'}}),
            element('a', {text: 'Kontakt', attributes: {href: '/seite/kontakt'}}),
            element('a', {text: 'Gästebuch', attributes: {href: '/seite/gaestebuch'}}),
            element('a', {text: 'Unterstützer', attributes: {href: '/seite/unterstuetzer'}}),
            element('a', {text: 'Redaktion', attributes: {href: '/admin'}}),
        ]}),
    ],
});

/**
 * Icon ganz rechts in der Kopfzeile (siehe `buildSiteHeader`) — bei Klick öffnet sich ein Pulldown
 * mit dem Link zu „Meine Mitgliedschaft" (`/meine-mitgliedschaft`, siehe `MEMBER_ACCESS_SLUG`,
 * `renderMemberSelfServicePage`).
 */
const buildMemberAccessNav = () => {
    const icon = element('span', {className: 'member-access-icon', attributes: {'aria-hidden': 'true'}});
    icon.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"></path></svg>';
    const toggle = element('button', {
        className: 'member-access-toggle',
        attributes: {type: 'button', 'aria-haspopup': 'true', 'aria-expanded': 'false'},
    });
    toggle.append(icon);
    const menu = element('div', {className: 'member-access-menu', children: [
        element('a', {text: 'Meine Mitgliedschaft', attributes: {href: '/' + MEMBER_ACCESS_SLUG}}),
    ]});
    const container = element('div', {className: 'member-access-nav', children: [toggle, menu]});
    const close = () => {
        container.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
    };
    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        const open = container.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });

    return container;
};

const renderPublic = async () => {
    try {
        const slug = app.dataset.pageSlug;
        const navigation = await request('/api/public/v1/navigation');
        const navigationTree = buildPageTree(navigation.items, false);

        if (slug === MEMBER_ACCESS_SLUG) {
            await renderMemberSelfServicePage(navigationTree);
            return;
        }

        const page = await request('/api/public/v1/pages/' + encodePageSlug(slug));
        updateDocumentMetadata(page);

        const publicContext = {visited: new Set([page.id]), pagesById: null, showEmbedErrors: false, isPreview: false};
        const article = renderContentCard(page, publicContext);
        if (slug === 'kontakt') article.append(renderContactForm());
        if (slug === 'gaestebuch') article.append(await renderGuestbook());

        app.replaceChildren(
            buildSiteHeader(navigationTree, slug, [buildMemberAccessNav()]),
            element('main', {
                className: 'page-shell',
                children: [
                    element('section', {
                        className: 'page-hero',
                        children: [
                            element('p', {className: 'eyebrow', text: 'Naturbad · Borkheide'}),
                            element('h1', {text: page.title}),
                            ...(page.seoDescription ? [element('p', {className: 'lead', text: page.seoDescription})] : []),
                        ],
                    }),
                    article,
                ],
            }),
            buildSiteFooter(),
        );
    } catch (error) {
        renderError(error.message);
    }
};

const memberDataRow = (label, value) => element('div', {className: 'member-data-row', children: [
    element('span', {className: 'member-data-label', text: label}),
    element('span', {className: 'member-data-value', text: value === null || value === undefined || value === '' ? '–' : value}),
]});

const formatDateDE = (isoDate) => isoDate ? new Date(isoDate + 'T00:00:00').toLocaleDateString('de-DE') : null;

/**
 * Beitrag + Arbeitseinsatz-Zuschlag eines einzelnen Mitglieds, aus den von der API gelieferten,
 * bereits in der DB hinterlegten Werten (keine Neuberechnung) — für die Haushaltssumme (siehe
 * `renderMemberSelfServiceTotal`) und die Kurzanzeige im Akkordeon-Kopf (siehe
 * `renderMemberSelfServiceData`).
 */
const memberTotalCents = (member) => (member.contributionAmountCents || 0) + (member.workAssignmentSurchargeCents || 0);

/**
 * Summe aus Beitrag + Arbeitseinsatz-Zuschlag über alle beitragspflichtigen Haushaltsmitglieder,
 * gruppiert nach Zahlungsintervall (i. d. R. nur eines, aber theoretisch könnten Haushaltsmitglieder
 * unterschiedliche Intervalle haben). `validFrom` (ISO-Datum oder null, siehe
 * `MemberAccessSessionResponse::$contributionRatesValidFrom`) verweist auf die zugrunde liegende
 * Beitragsordnung.
 */
/**
 * Fasst beitragspflichtige Haushaltsmitglieder nach Beitragssatz-Label + Betrag + Zahlungsintervall
 * zusammen, z. B. "2x Familie: Elternteil" oder "3x Arbeitseinsatz-Zuschlag" — für die
 * Kurzübersicht in `renderMemberSelfServiceTotal`.
 */
const groupMembersByAmount = (members, labelFn, amountFn) => {
    const groups = new Map();
    members.forEach((member) => {
        const label = labelFn(member);
        const cents = amountFn(member);
        if (!label || !cents) return;
        const key = `${label}|${cents}|${member.paymentInterval}`;
        const group = groups.get(key) || {label, cents, interval: member.paymentInterval, count: 0};
        group.count += 1;
        groups.set(key, group);
    });

    return [...groups.values()];
};

const formatHoursMinutes = (totalMinutes) => {
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return minutes > 0 ? `${hours} Std ${minutes} Min` : `${hours} Std`;
};

/**
 * Arbeitseinsatz-Gutschrift der ganzen Familie (siehe `WorkAssignmentCreditCalculator`, geleistete
 * Stunden über alle Haushaltsmitglieder aufsummiert — innerhalb der Familie übertragbar). Bewusst
 * getrennt vom „Gesamtbeitrag" oben dargestellt: die Gutschrift wird dort **nicht** abgezogen,
 * sondern ist eine gesonderte Rückzahlung.
 */
const renderWorkAssignmentCredit = (credit) => element('div', {className: 'member-self-service-work-assignment', children: [
    element('h4', {text: 'Geleistete Arbeitsstunden'}),
    memberDataRow('Zeitraum', `${formatDateDE(credit.periodFrom)} – ${formatDateDE(credit.periodTo)}`),
    memberDataRow('Arbeitseinsätze der Familie', `${credit.liableMemberCount}x (${formatEuro(credit.totalSurchargeCents)})`),
    memberDataRow('Benötigte Stunden je Arbeitseinsatz', `${credit.requiredHoursPerAssignment} Std (${formatEuro(credit.creditPerHourCents)}/Std)`),
    memberDataRow('Geleistete Stunden der ganzen Familie', formatHoursMinutes(credit.workedMinutes)),
    element('div', {className: 'member-payer-total', children: [
        element('span', {text: 'Gutschrift (gesonderte Rückzahlung)'}),
        element('span', {text: formatEuro(credit.creditCents)}),
    ]}),
]});

const renderMemberSelfServiceTotal = (members, validFrom, workAssignmentCredit) => {
    const liableMembers = members.filter((member) => member.contributionLiable);
    const totalsByInterval = new Map();
    liableMembers.forEach((member) => {
        totalsByInterval.set(member.paymentInterval, (totalsByInterval.get(member.paymentInterval) || 0) + memberTotalCents(member));
    });

    const breakdownGroups = [
        ...groupMembersByAmount(liableMembers, (member) => member.contributionCategoryLabel, (member) => member.contributionAmountCents),
        ...groupMembersByAmount(liableMembers, () => 'Arbeitseinsatz-Zuschlag', (member) => member.workAssignmentSurchargeCents),
    ];

    return element('article', {className: 'management-card member-self-service-total', children: [
        element('h3', {text: 'Gesamtbeitrag'}),
        liableMembers.length
            ? [
                ...breakdownGroups.map((group) => memberDataRow(`${group.count}x ${group.label}`, `${formatEuro(group.cents)} (${PAYMENT_INTERVAL_LABELS[group.interval] || group.interval})`)),
                ...[...totalsByInterval].map(([interval, cents]) => element('div', {className: 'member-payer-total', children: [
                    element('span', {text: 'Gesamtbeitrag'}),
                    element('span', {text: `${formatEuro(cents)} (${PAYMENT_INTERVAL_LABELS[interval] || interval})`}),
                ]})),
            ]
            : element('p', {className: 'empty-copy', text: 'Kein beitragspflichtiges Mitglied in diesem Haushalt.'}),
        ...(validFrom ? [element('p', {className: 'field-hint', text: `Beitragsordnung / Beitragssätze – gültig ab ${formatDateDE(validFrom)}`})] : []),
        ...(workAssignmentCredit && workAssignmentCredit.liableMemberCount > 0 ? [renderWorkAssignmentCredit(workAssignmentCredit)] : []),
    ].flat()});
};

/**
 * Nur lesende Ansicht der eigenen Mitgliedsdaten (Stammdaten/Kontaktdaten/Vereinsdaten/
 * Beitragsdaten, siehe `MemberSelfServiceResponse`) für jede über den Token erreichbare Person
 * (i. d. R. der ganze Haushalt, siehe `RequestMemberAccessUseCase`) als aufklappbares Akkordeon
 * (nur bei genau einer Person direkt geöffnet) — Kopfzeile links Mitgliedsnummer/Name, rechts die
 * Gesamtkosten dieses Mitglieds —, sowie ein Formular, um dem Verein eine Nachricht zu schicken
 * (siehe `SendMemberMessageUseCase`).
 */
const renderMemberSelfServiceData = (token, password, session) => {
    // Ältestes Haushaltsmitglied zuerst (Nutzer-Vorgabe) — bestimmt sowohl die Akkordeon- als auch
    // die Pulldown-Reihenfolge; `birthDate` ist ein ISO-Datum ("YYYY-MM-DD"), daher reicht ein
    // aufsteigender String-Vergleich.
    const sortedMembers = [...session.members].sort((a, b) => a.birthDate.localeCompare(b.birthDate));

    const memberCards = sortedMembers.map((member, index) => {
        const card = element('details', {className: 'member-self-service-card', children: [
            element('summary', {children: [
                element('span', {className: 'member-self-service-summary-identity', children: [
                    element('span', {className: 'member-self-service-summary-number', text: member.memberNumber}),
                    element('span', {className: 'member-self-service-summary-name', text: `${member.firstName} ${member.lastName}`}),
                ]}),
                element('span', {className: 'member-self-service-summary-total', text: member.contributionLiable
                    ? `${formatEuro(memberTotalCents(member))} (${PAYMENT_INTERVAL_LABELS[member.paymentInterval] || member.paymentInterval})`
                    : 'Beitragsfrei'}),
            ]}),
            element('div', {className: 'member-self-service-card-body', children: [
                element('h4', {text: 'Stammdaten'}),
                memberDataRow('Mitgliedsnummer', member.memberNumber),
                memberDataRow('Anrede', SALUTATION_LABELS[member.salutation] || member.salutation),
                memberDataRow('Geburtsdatum', formatDateDE(member.birthDate)),
                memberDataRow('Rolle in der Familie', FAMILY_ROLE_LABELS[member.familyRole] || member.familyRole),
                element('h4', {text: 'Kontaktdaten'}),
                memberDataRow('Adresse', `${member.street}, ${member.postalCode} ${member.city}`),
                memberDataRow('E-Mail', member.email),
                memberDataRow('Telefon', member.phone),
                element('h4', {text: 'Vereinsdaten'}),
                memberDataRow('Funktion', MEMBER_FUNCTION_LABELS[member.function] || member.function),
                memberDataRow('Status', member.active ? 'Aktives Mitglied' : 'Nicht mehr aktiv'),
                memberDataRow('Mitglied seit', formatDateDE(member.joinedAt)),
                ...(member.leftAt ? [memberDataRow('Austrittsdatum', formatDateDE(member.leftAt))] : []),
                element('h4', {text: 'Beitragsdaten'}),
                memberDataRow('Beitragspflichtig', member.contributionLiable ? 'Ja' : 'Nein (z. B. Vorstand)'),
                ...(member.contributionLiable ? [
                    ...(member.contributionCategoryLabel ? [memberDataRow('Beitragssatz', member.contributionCategoryLabel)] : []),
                    memberDataRow('Beitrag', `${formatEuro(member.contributionAmountCents)} (${PAYMENT_INTERVAL_LABELS[member.paymentInterval] || member.paymentInterval})`),
                    ...(member.workAssignmentSurchargeCents ? [memberDataRow('Arbeitseinsatz-Zuschlag', `${formatEuro(member.workAssignmentSurchargeCents)} (${PAYMENT_INTERVAL_LABELS[member.paymentInterval] || member.paymentInterval})`)] : []),
                    memberDataRow('Zahlweise', PAYMENT_METHOD_LABELS[member.paymentMethod] || member.paymentMethod),
                ] : []),
            ]}),
        ]});
        // Das erste (= älteste) Akkordeon ist aufgeklappt, alle anderen zugeklappt.
        if (index === 0) card.open = true;

        return card;
    });

    const memberSelect = element('select', {attributes: {name: 'memberId', id: 'member-message-member'}});
    sortedMembers.forEach((member) => memberSelect.append(element('option', {
        text: `${member.firstName} ${member.lastName} (${member.memberNumber})`,
        attributes: {value: member.id},
    })));
    const memberField = sortedMembers.length > 1
        ? element('label', {className: 'field', children: [element('span', {text: 'Für welche Person?'}), memberSelect]})
        : element('input', {attributes: {type: 'hidden', name: 'memberId', value: sortedMembers[0].id}});

    const message = formMessage();
    const messageForm = element('form', {className: 'public-form', children: [
        element('h2', {text: 'Nachricht senden'}),
        element('p', {text: 'Hat sich etwas geändert (z. B. Adresse, Telefonnummer) oder stimmt etwas nicht mehr? Schreib uns kurz.'}),
        memberField,
        field('Nachricht', 'message', '', 'textarea'),
        message,
        element('button', {className: 'button', text: 'Nachricht senden', attributes: {type: 'submit'}}),
    ]});
    messageForm.querySelector('[name="message"]').required = true;
    messageForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(messageForm);
        const button = messageForm.querySelector('button');
        button.disabled = true;
        try {
            const result = await request('/api/public/v1/member-access/messages', {method: 'POST', body: JSON.stringify({
                token, password, memberId: data.get('memberId'), message: data.get('message'),
            })});
            messageForm.reset();
            message.textContent = result.message;
            message.classList.add('success');
            toast(result.message);
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    return element('div', {className: 'member-self-service', children: [
        element('p', {className: 'field-hint', text: `Angemeldet mit ${session.email}`}),
        renderMemberSelfServiceTotal(session.members, session.contributionRatesValidFrom, session.workAssignmentCredit),
        ...memberCards,
        messageForm,
    ]});
};

/**
 * Fragt vor dem Anzeigen der Mitgliedsdaten das aus der Mail bekannte Passwort ab (zweiter Faktor
 * neben dem Token aus der URL, siehe `MemberAccessToken`) — es gibt keine serverseitige Sitzung,
 * daher wird das Passwort clientseitig gehalten und bei der Nachricht (`renderMemberSelfServiceData`)
 * erneut mitgeschickt statt nur einmalig geprüft. Passt Token oder Passwort nicht (z. B. auch
 * abgelaufen), erscheint dieselbe Fehlermeldung samt Link, um einen neuen Zugang anzufordern —
 * ohne erkennbaren Unterschied, welcher der beiden Faktoren nicht gepasst hat.
 */
const renderMemberSelfServicePasswordGate = (token) => {
    const container = element('div');
    const message = formMessage();
    const passwordField = field('Passwort', 'password', '', 'password');
    const form = element('form', {className: 'public-form', children: [
        element('h2', {text: 'Meine Mitgliedschaft'}),
        element('p', {text: 'Gib das Passwort aus der E-Mail ein, um deine Daten zu sehen.'}),
        passwordField,
        message,
        element('button', {className: 'button', text: 'Bestätigen', attributes: {type: 'submit'}}),
        element('a', {className: 'button', text: 'Neuen Zugang anfordern', attributes: {href: '/' + MEMBER_ACCESS_SLUG}}),
    ]});
    passwordField.querySelector('input').required = true;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const password = new FormData(form).get('password');
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const session = await request('/api/public/v1/member-access/sessions', {method: 'POST', body: JSON.stringify({token, password})});
            container.replaceChildren(renderMemberSelfServiceData(token, password, session));
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
            button.disabled = false;
        }
    });

    container.append(form);

    return container;
};

/**
 * Formular, um über die eigene E-Mail-Adresse + das eigene Geburtsdatum einen 30 Minuten gültigen
 * Zugangslink anzufordern (siehe `RequestMemberAccessUseCase`) — bewusst immer dieselbe
 * Erfolgsmeldung, unabhängig davon, ob die Kombination zu einem Mitglied gehört. Das Geburtsdatum
 * ist zusätzlich zur E-Mail-Adresse nötig, da diese meist für den ganzen Haushalt gleich ist.
 */
const renderMemberAccessRequestForm = () => {
    const message = formMessage();
    const emailField = field('E-Mail-Adresse', 'email', '', 'email');
    const birthDateField = field('Geburtsdatum', 'birthDate', '', 'date');
    const form = element('form', {className: 'public-form', children: [
        element('h2', {text: 'Zugang anfordern'}),
        element('p', {text: 'Gib die E-Mail-Adresse und das Geburtsdatum ein, die bei deiner Mitgliedschaft hinterlegt sind. Du erhältst per Mail einen Link, der 30 Minuten gültig ist.'}),
        emailField,
        birthDateField,
        message,
        element('button', {className: 'button', text: 'Zugangslink anfordern', attributes: {type: 'submit'}}),
    ]});
    emailField.querySelector('input').required = true;
    birthDateField.querySelector('input').required = true;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const result = await request('/api/public/v1/member-access/tokens', {method: 'POST', body: JSON.stringify({email: data.get('email'), birthDate: data.get('birthDate')})});
            form.reset();
            message.textContent = result.message;
            message.classList.add('success');
            toast(result.message);
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    return form;
};

/**
 * „Meine Mitgliedschaft" (siehe `MEMBER_ACCESS_SLUG`, `buildMemberAccessNav`) — eigenständige
 * Ansicht außerhalb des CMS-Seitenbaums: ohne `?token=` das Anfrageformular, mit Token die
 * Passwortabfrage vor den Mitgliedsdaten (siehe `renderMemberSelfServicePasswordGate`).
 */
const renderMemberSelfServicePage = async (navigationTree) => {
    const token = new URLSearchParams(window.location.search).get('token');
    const content = token ? renderMemberSelfServicePasswordGate(token) : renderMemberAccessRequestForm();

    app.replaceChildren(
        buildSiteHeader(navigationTree, MEMBER_ACCESS_SLUG, [buildMemberAccessNav()]),
        element('main', {
            className: 'page-shell',
            children: [
                element('section', {
                    className: 'page-hero',
                    children: [
                        element('p', {className: 'eyebrow', text: 'Naturbad · Borkheide'}),
                        element('h1', {text: 'Meine Mitgliedschaft'}),
                    ],
                }),
                element('div', {className: 'content-card', children: [content]}),
            ],
        }),
        buildSiteFooter(),
    );
};

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

const parentPageField = (pages, page, initialParentId) => {
    const select = element('select', {attributes: {name: 'parentId', id: 'parentId'}});
    select.append(element('option', {text: 'Keine – Hauptseite', attributes: {value: ''}}));

    const childIds = new Set();
    const collectChildren = (parentId) => pages.filter((candidate) => candidate.parentId === parentId).forEach((child) => {
        childIds.add(child.id);
        collectChildren(child.id);
    });
    if (page) collectChildren(page.id);

    flattenPageTree(buildPageTree(pages)).forEach(({page: candidate, depth}) => {
        if (candidate.id === page?.id || childIds.has(candidate.id)) return;
        select.append(element('option', {
            text: `${'— '.repeat(depth)}${candidate.title}`,
            attributes: {value: candidate.id},
        }));
    });
    select.value = page?.parentId || initialParentId || '';

    return element('label', {className: 'field', children: [element('span', {text: 'Übergeordnete Seite'}), select]});
};

const renderLogin = () => {
    app.onkeydown = null;
    const message = element('p', {className: 'form-message', attributes: {'aria-live': 'polite'}});
    const form = element('form', {
        className: 'login-card',
        children: [
            element('p', {className: 'eyebrow', text: 'Waldbad Borkheide'}),
            element('h1', {text: 'Redaktion'}),
            element('p', {text: 'Melde dich an, um Inhalte zu bearbeiten.'}),
            field('E-Mail-Adresse', 'email', '', 'email'),
            field('Passwort', 'password', '', 'password'),
            message,
            element('button', {className: 'button', text: 'Anmelden', attributes: {type: 'submit'}}),
        ],
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        try {
            await request('/api/auth/v1/login', {
                method: 'POST',
                body: JSON.stringify({email: data.get('email'), password: data.get('password')}),
            });
            toast('Erfolgreich angemeldet.');
            await renderAdmin();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
    });
    app.replaceChildren(element('main', {className: 'login-shell', children: [form]}));
};

const richTextEditor = (block, index, onChange = null, ariaLabel = 'Rich-Text-Inhalt') => {
    const editor = element('div', {
        className: 'rich-text-surface',
        attributes: {contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-label': ariaLabel},
    });
    editor.innerHTML = block.content || '';
    const source = element('textarea', {
        className: 'html-source',
        attributes: {id: 'block-content-' + index, 'aria-label': 'HTML-Quelltext'},
    });
    source.hidden = true;

    const syncVisual = () => {
        block.content = editor.innerHTML;
        onChange?.(block.content);
    };
    const run = (command, value = null) => {
        editor.focus();
        document.execCommand(command, false, value);
        syncVisual();
    };
    const toolbarButton = (label, title, command) => {
        const button = element('button', {className: 'editor-tool', text: label, attributes: {type: 'button', title, 'aria-label': title}});
        button.addEventListener('click', () => run(command));
        return button;
    };

    const format = element('select', {attributes: {'aria-label': 'Textformat', title: 'Textformat'}});
    [['p', 'Absatz'], ['h2', 'Überschrift 2'], ['h3', 'Überschrift 3'], ['blockquote', 'Zitat']].forEach(([value, label]) => {
        format.append(element('option', {text: label, attributes: {value}}));
    });
    format.addEventListener('change', () => run('formatBlock', format.value));

    const size = element('select', {attributes: {'aria-label': 'Textgröße', title: 'Textgröße'}});
    [['2', 'Klein'], ['3', 'Normal'], ['4', 'Groß'], ['5', 'Sehr groß']].forEach(([value, label]) => {
        size.append(element('option', {text: label, attributes: {value}}));
    });
    size.value = '3';
    size.addEventListener('change', () => run('fontSize', size.value));

    const color = element('input', {attributes: {type: 'color', value: '#174f35', title: 'Textfarbe', 'aria-label': 'Textfarbe'}});
    color.addEventListener('input', () => run('foreColor', color.value));

    const link = element('button', {className: 'editor-tool', text: 'Link', attributes: {type: 'button', title: 'Link einfügen'}});
    link.addEventListener('click', () => {
        const url = window.prompt('Zieladresse des Links:');
        if (url) run('createLink', url);
    });

    const table = element('button', {className: 'editor-tool', text: 'Tabelle', attributes: {type: 'button', title: 'Tabelle einfügen'}});
    table.addEventListener('click', () => {
        const selection = window.getSelection();
        const selectedRange = selection?.rangeCount && editor.contains(selection.anchorNode)
            ? selection.getRangeAt(0).cloneRange()
            : null;
        const dialog = element('dialog', {className: 'table-dialog'});
        const rows = element('input', {attributes: {type: 'number', min: '1', max: '20', value: '3', required: 'required'}});
        const columns = element('input', {attributes: {type: 'number', min: '1', max: '8', value: '2', required: 'required'}});
        const header = element('input', {attributes: {type: 'checkbox', checked: 'checked'}});
        header.checked = true;
        const stripedRows = element('input', {attributes: {type: 'checkbox'}});
        const form = element('form', {className: 'table-form', attributes: {method: 'dialog'}, children: [
            element('div', {children: [element('p', {className: 'eyebrow', text: 'Rich Text'}), element('h2', {text: 'Tabelle einfügen'})]}),
            element('div', {className: 'form-grid', children: [
                element('label', {className: 'field', children: [element('span', {text: 'Zeilen'}), rows]}),
                element('label', {className: 'field', children: [element('span', {text: 'Spalten'}), columns]}),
            ]}),
            element('label', {className: 'check-field', children: [header, element('span', {text: 'Erste Zeile als Kopfzeile'})]}),
            element('label', {className: 'check-field', children: [stripedRows, element('span', {text: 'Zeilen abwechselnd einfärben'})]}),
            element('div', {className: 'editor-actions', children: [
                element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}}),
                element('button', {className: 'button', text: 'Tabelle einfügen', attributes: {type: 'submit'}}),
            ]}),
        ]});
        form.querySelector('.secondary-button').addEventListener('click', () => dialog.close());
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const rowCount = Math.max(1, Math.min(20, Number.parseInt(rows.value, 10) || 1));
            const columnCount = Math.max(1, Math.min(8, Number.parseInt(columns.value, 10) || 1));
            const tableElement = document.createElement('table');
            if (stripedRows.checked) tableElement.classList.add('table-striped');
            const body = document.createElement('tbody');
            if (header.checked) {
                const head = document.createElement('thead');
                const row = document.createElement('tr');
                for (let columnIndex = 0; columnIndex < columnCount; columnIndex += 1) {
                    const cell = document.createElement('th');
                    cell.textContent = `Spalte ${columnIndex + 1}`;
                    row.append(cell);
                }
                head.append(row);
                tableElement.append(head);
            }
            const bodyRowCount = header.checked ? Math.max(1, rowCount - 1) : rowCount;
            for (let rowIndex = 0; rowIndex < bodyRowCount; rowIndex += 1) {
                const row = document.createElement('tr');
                for (let columnIndex = 0; columnIndex < columnCount; columnIndex += 1) {
                    const cell = document.createElement('td');
                    cell.textContent = 'Inhalt';
                    row.append(cell);
                }
                body.append(row);
            }
            tableElement.append(body);
            editor.focus();
            if (selectedRange) {
                const currentSelection = window.getSelection();
                currentSelection?.removeAllRanges();
                currentSelection?.addRange(selectedRange);
            }
            document.execCommand('insertHTML', false, `${tableElement.outerHTML}<p><br></p>`);
            syncVisual();
            dialog.close();
        });
        dialog.addEventListener('close', () => dialog.remove());
        dialog.append(form);
        document.body.append(dialog);
        dialog.showModal();
    });

    const toggle = element('button', {className: 'editor-tool html-toggle', text: 'HTML', attributes: {type: 'button', title: 'HTML-Quelltext bearbeiten'}});
    let htmlMode = false;
    toggle.addEventListener('click', () => {
        htmlMode = !htmlMode;
        if (htmlMode) {
            source.value = editor.innerHTML;
            editor.hidden = true;
            source.hidden = false;
            toggle.textContent = 'Visuell';
            source.focus();
        } else {
            editor.innerHTML = source.value;
            block.content = source.value;
            onChange?.(block.content);
            source.hidden = true;
            editor.hidden = false;
            toggle.textContent = 'HTML';
            editor.focus();
        }
    });
    editor.addEventListener('input', syncVisual);
    editor.addEventListener('paste', (event) => {
        event.preventDefault();
        const plainText = event.clipboardData?.getData('text/plain') || '';
        if (!document.execCommand('insertText', false, plainText)) {
            const selection = window.getSelection();
            if (selection?.rangeCount) {
                const range = selection.getRangeAt(0);
                range.deleteContents();
                const textNode = document.createTextNode(plainText);
                range.insertNode(textNode);
                range.setStartAfter(textNode);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        }
        syncVisual();
    });
    source.addEventListener('input', () => {
        block.content = source.value;
        onChange?.(block.content);
    });

    const toolbar = element('div', {className: 'rich-text-toolbar', attributes: {role: 'toolbar', 'aria-label': 'Text formatieren'}, children: [
        format,
        size,
        toolbarButton('B', 'Fett', 'bold'),
        toolbarButton('I', 'Kursiv', 'italic'),
        toolbarButton('U', 'Unterstrichen', 'underline'),
        toolbarButton('• Liste', 'Aufzählung', 'insertUnorderedList'),
        toolbarButton('1. Liste', 'Nummerierte Liste', 'insertOrderedList'),
        link,
        toolbarButton('Link lösen', 'Link entfernen', 'unlink'),
        table,
        color,
        toolbarButton('Format löschen', 'Formatierung entfernen', 'removeFormat'),
        toggle,
    ]});

    return element('div', {className: 'rich-text-editor', children: [toolbar, editor, source]});
};

const openImagePicker = async (onSelect) => {
    const dialog = element('dialog', {className: 'media-dialog'});
    const close = element('button', {className: 'text-button', text: 'Schließen', attributes: {type: 'button'}});
    close.addEventListener('click', () => dialog.close());
    const content = element('div', {className: 'media-grid', children: [element('p', {text: 'Bilder werden geladen …'})]});
    dialog.append(
        element('header', {className: 'media-dialog-header', children: [
            element('div', {children: [element('p', {className: 'eyebrow', text: 'Medien'}), element('h2', {text: 'Bild auswählen'})]}),
            close,
        ]}),
        content,
    );
    dialog.addEventListener('close', () => dialog.remove());
    document.body.append(dialog);
    dialog.showModal();

    try {
        const media = await request('/api/admin/v1/media/images');
        if (!media.items.length) {
            content.replaceChildren(emptyState('Es wurden noch keine Bilder hochgeladen.'));
            return;
        }
        content.replaceChildren(...media.items.map((image) => {
            const choose = element('button', {className: 'secondary-button full', text: 'Auswählen', attributes: {type: 'button'}});
            choose.addEventListener('click', () => {
                onSelect(image);
                dialog.close();
            });
            return element('article', {className: 'media-card', children: [
                element('img', {attributes: {src: image.url, alt: '', loading: 'lazy'}}),
                element('strong', {text: image.originalName}),
                element('small', {text: `${image.width} × ${image.height} px`}),
                ...(image.source ? [element('small', {className: 'media-card-source', text: `Quelle: ${image.source}`})] : []),
                choose,
            ]});
        }));
    } catch (error) {
        content.replaceChildren(emptyState(error.message));
    }
};

const collectionItemMediaEditor = (item, key) => {
    const media = field('Bild-URL (optional)', `collection-media-${key}`, item.mediaUrl || '');
    const alt = field('Alternativtext (optional; leer = dekorativ)', `collection-alt-${key}`, item.mediaAlt || '');
    const source = field('Bildquelle (optional)', `collection-source-${key}`, item.mediaSource || '');
    const mediaInput = media.querySelector('input');
    const altInput = alt.querySelector('input');
    const sourceInput = source.querySelector('input');
    sourceInput.maxLength = 300;
    let storedSource = item.mediaSource || null;
    mediaInput.addEventListener('input', () => item.mediaUrl = mediaInput.value || null);
    altInput.addEventListener('input', () => item.mediaAlt = altInput.value || null);
    sourceInput.addEventListener('input', () => item.mediaSource = sourceInput.value || null);
    sourceInput.addEventListener('blur', async () => {
        const normalizedSource = sourceInput.value.trim() || null;
        if (!item.mediaUrl?.startsWith('/uploads/media/') || normalizedSource === storedSource) return;
        try {
            const updated = await request('/api/admin/v1/media/images/source', {
                method: 'PATCH',
                body: JSON.stringify({url: item.mediaUrl, source: normalizedSource}),
            });
            storedSource = updated.source;
            item.mediaSource = updated.source;
            sourceInput.value = updated.source || '';
            toast('Die Bildquelle wurde in der Medienbibliothek aktualisiert.');
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    const uploadInput = element('input', {attributes: {type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', hidden: 'hidden'}});
    const uploadButton = element('button', {className: 'secondary-button', text: 'Bild hochladen', attributes: {type: 'button'}});
    const selectButton = element('button', {className: 'secondary-button', text: 'Bild auswählen', attributes: {type: 'button'}});
    const uploadMessage = element('small', {className: 'upload-message', attributes: {'aria-live': 'polite'}});
    uploadButton.addEventListener('click', () => uploadInput.click());
    selectButton.addEventListener('click', () => openImagePicker((image) => {
        item.mediaUrl = image.url;
        item.mediaSource = image.source || null;
        mediaInput.value = image.url;
        sourceInput.value = image.source || '';
        storedSource = image.source || null;
        uploadMessage.textContent = `${image.originalName} wurde ausgewählt.`;
        toast(`${image.originalName} wurde ausgewählt.`);
    }));
    uploadInput.addEventListener('change', async () => {
        const image = uploadInput.files?.[0];
        if (!image) return;
        const body = new FormData();
        body.append('image', image);
        body.append('source', sourceInput.value.trim());
        uploadButton.disabled = true;
        uploadMessage.textContent = 'Bild wird hochgeladen …';
        try {
            const stored = await request('/api/admin/v1/media/images', {method: 'POST', body});
            item.mediaUrl = stored.url;
            item.mediaSource = stored.source || null;
            mediaInput.value = stored.url;
            sourceInput.value = stored.source || '';
            storedSource = stored.source || null;
            uploadMessage.textContent = `${stored.originalName} wurde hochgeladen (${stored.width} × ${stored.height} px).`;
            toast(`${stored.originalName} wurde hochgeladen.`);
        } catch (error) {
            uploadMessage.textContent = error.message;
            toast(error.message, 'error');
        } finally {
            uploadButton.disabled = false;
            uploadInput.value = '';
        }
    });

    return element('div', {className: 'collection-media-editor', children: [
        element('div', {className: 'media-input-row', children: [media, selectButton, uploadButton, uploadInput]}),
        uploadMessage,
        alt,
        source,
        element('small', {text: 'Bei Bibliotheksbildern wird die gespeicherte Quelle automatisch übernommen.'}),
    ]});
};

const blockEditor = (block, index, handlers) => {
    const card = element('section', {className: 'block-editor'});
    const moveUp = element('button', {className: 'editor-tool', text: '↑', attributes: {type: 'button', title: 'Block nach oben', 'aria-label': 'Block nach oben'}});
    const moveDown = element('button', {className: 'editor-tool', text: '↓', attributes: {type: 'button', title: 'Block nach unten', 'aria-label': 'Block nach unten'}});
    moveUp.disabled = index === 0;
    moveDown.disabled = index === handlers.lastIndex;
    moveUp.addEventListener('click', () => handlers.onMove(index, index - 1));
    moveDown.addEventListener('click', () => handlers.onMove(index, index + 1));
    const dragLabel = element('div', {className: 'drag-label', attributes: {draggable: 'true', title: 'Block ziehen'}, children: [
        element('span', {text: '↕', attributes: {'aria-hidden': 'true'}}),
        element('strong', {text: BLOCK_TYPES[block.type] || block.type}),
    ]});
    dragLabel.addEventListener('dragstart', (event) => {
        card.classList.add('dragging');
        event.dataTransfer?.setData('text/plain', String(index));
        handlers.onDragStart(index);
    });
    dragLabel.addEventListener('dragend', () => {
        card.classList.remove('dragging');
        handlers.onDragEnd();
    });
    card.addEventListener('dragover', (event) => {
        event.preventDefault();
        card.classList.add('drag-over');
    });
    card.addEventListener('dragleave', () => card.classList.remove('drag-over'));
    card.addEventListener('drop', (event) => {
        event.preventDefault();
        card.classList.remove('drag-over');
        handlers.onDrop(index);
    });
    card.append(element('div', {className: 'block-editor-heading', children: [
        dragLabel,
        element('div', {className: 'block-move-actions', children: [moveUp, moveDown]}),
    ]}));
    if (block.type === 'feature_collection') {
        block.collectionColumns = Number.isInteger(block.collectionColumns) ? block.collectionColumns : 3;
        block.collectionItems = Array.isArray(block.collectionItems) ? block.collectionItems : [];
        const heading = field('Collection-Überschrift', `block-collection-heading-${index}`, block.content || '');
        const headingInput = heading.querySelector('input');
        headingInput.required = true;
        headingInput.addEventListener('input', () => block.content = headingInput.value);
        const columns = element('select', {
            attributes: {id: `block-collection-columns-${index}`},
            children: [1, 2, 3, 4].map((count) => element('option', {
                text: `${count} ${count === 1 ? 'Spalte' : 'Spalten'}`,
                attributes: {value: String(count)},
            })),
        });
        columns.value = String(block.collectionColumns);
        columns.addEventListener('change', () => block.collectionColumns = Number.parseInt(columns.value, 10));
        const itemList = element('div', {className: 'collection-item-editor-list'});
        const renderItems = () => {
            if (block.collectionItems.length === 0) {
                itemList.replaceChildren(emptyState('Noch keine Einträge vorhanden. Füge den ersten Eintrag hinzu.'));
                return;
            }
            itemList.replaceChildren(...block.collectionItems.map((item, itemIndex) => {
                const title = field('Überschrift', `collection-title-${index}-${itemIndex}`, item.title || '');
                const titleInput = title.querySelector('input');
                titleInput.required = true;
                titleInput.maxLength = 160;
                titleInput.addEventListener('input', () => item.title = titleInput.value);
                const moveUp = element('button', {className: 'tree-icon-button', text: '↑', attributes: {type: 'button', title: 'Eintrag nach oben', 'aria-label': 'Eintrag nach oben'}});
                const moveDown = element('button', {className: 'tree-icon-button', text: '↓', attributes: {type: 'button', title: 'Eintrag nach unten', 'aria-label': 'Eintrag nach unten'}});
                const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', title: 'Eintrag entfernen', 'aria-label': 'Eintrag entfernen'}});
                moveUp.disabled = itemIndex === 0;
                moveDown.disabled = itemIndex === block.collectionItems.length - 1;
                moveUp.addEventListener('click', () => {
                    [block.collectionItems[itemIndex - 1], block.collectionItems[itemIndex]] = [block.collectionItems[itemIndex], block.collectionItems[itemIndex - 1]];
                    renderItems();
                });
                moveDown.addEventListener('click', () => {
                    [block.collectionItems[itemIndex], block.collectionItems[itemIndex + 1]] = [block.collectionItems[itemIndex + 1], block.collectionItems[itemIndex]];
                    renderItems();
                });
                remove.addEventListener('click', async () => {
                    const itemLabel = item.title || `Eintrag ${itemIndex + 1}`;
                    const confirmed = await confirmAction(
                        `„${itemLabel}“ entfernen?`,
                        'Der Collection-Eintrag mit Bild und Text wird entfernt. Die Änderung wird mit dem nächsten Speichern dauerhaft.',
                        'Eintrag entfernen',
                    );
                    if (!confirmed) return;
                    block.collectionItems.splice(itemIndex, 1);
                    renderItems();
                    toast('Collection-Eintrag wurde entfernt.');
                });

                return element('section', {className: 'collection-item-editor', children: [
                    element('header', {className: 'collection-item-editor-heading', children: [
                        element('strong', {text: `Eintrag ${itemIndex + 1}`}),
                        element('div', {className: 'block-move-actions', children: [moveUp, moveDown, remove]}),
                    ]}),
                    title,
                    element('div', {className: 'field', children: [
                        element('span', {text: 'Text (optional)'}),
                        richTextEditor(item, `${index}-collection-${itemIndex}`, null, `Text für ${item.title || `Eintrag ${itemIndex + 1}`}`),
                    ]}),
                    collectionItemMediaEditor(item, `${index}-${itemIndex}`),
                ]});
            }));
        };
        const addItem = element('button', {className: 'secondary-button', text: '＋ Eintrag hinzufügen', attributes: {type: 'button'}});
        addItem.addEventListener('click', () => {
            block.collectionItems.push(createCollectionItem());
            renderItems();
            itemList.lastElementChild?.querySelector('input')?.focus();
        });
        renderItems();
        card.append(
            heading,
            element('label', {className: 'field collection-columns-field', children: [element('span', {text: 'Spalten im Desktop-Grid'}), columns]}),
            element('small', {text: 'Auf kleinen Bildschirmen werden die Karten automatisch untereinander dargestellt.'}),
            itemList,
            addItem,
        );
    } else if (block.type === 'event') {
        const title = field('Veranstaltungsüberschrift', 'block-event-title-' + index, block.eventTitle || '');
        const date = field('Veranstaltungsdatum', 'block-event-date-' + index, block.eventDate || '', 'date');
        const time = field('Uhrzeit', 'block-event-time-' + index, block.eventTime || '14:00', 'time');
        const titleInput = title.querySelector('input');
        const dateInput = date.querySelector('input');
        const timeInput = time.querySelector('input');
        titleInput.required = true;
        dateInput.required = true;
        timeInput.required = true;
        titleInput.addEventListener('input', (event) => block.eventTitle = event.target.value || null);
        dateInput.addEventListener('input', (event) => block.eventDate = event.target.value || null);
        timeInput.addEventListener('input', (event) => block.eventTime = event.target.value || null);
        card.append(
            title,
            element('div', {className: 'form-grid', children: [date, time]}),
            element('div', {className: 'field', children: [
                element('span', {text: 'Zusatzinformationen zur Veranstaltung (optional)'}),
                richTextEditor(block, index + '-event-details', null, 'Zusatzinformationen zur Veranstaltung'),
            ]}),
        );
        const helpEnabled = element('input', {attributes: {type: 'checkbox', id: 'block-event-help-' + index}});
        helpEnabled.checked = block.eventHelpEnabled === true;
        const helpLabel = field('Beschriftung des Buttons', 'block-event-help-label-' + index, block.eventHelpButtonLabel || 'Ich möchte helfen!');
        const helpLabelInput = helpLabel.querySelector('input');
        helpLabelInput.addEventListener('input', () => block.eventHelpButtonLabel = helpLabelInput.value || 'Ich möchte helfen!');
        card.append(
            element('label', {className: 'check-field event-help-option', children: [
                helpEnabled,
                element('span', {text: 'Im Frontend den Button „Ich möchte helfen!“ mit Anmeldeformular anzeigen'}),
            ]}),
        );
        const activityList = element('div', {className: 'event-activity-editor-list'});
        const renderAssignments = () => {
            block.eventActivities = Array.isArray(block.eventActivities) ? block.eventActivities : [];
            activityList.replaceChildren(...block.eventActivities.map((assignment, assignmentIndex) => {
                const select = element('select', {attributes: {'aria-label': 'Aktivität'}});
                (handlers.activities || []).forEach((activity) => {
                    if (!activity.active && activity.id !== assignment.activityId) return;
                    if (activity.id !== assignment.activityId && block.eventActivities.some((item) => item.activityId === activity.id)) return;
                    select.append(element('option', {text: `${activity.name}${activity.active ? '' : ' (inaktiv)'}`, attributes: {value: activity.id}}));
                });
                select.value = assignment.activityId;
                select.addEventListener('change', () => assignment.activityId = select.value);
                const count = element('input', {attributes: {
                    type: 'number', min: '1', max: '999', value: String(assignment.requiredHelpers || 1),
                    'aria-label': 'Benötigte Helfer',
                }});
                const decrease = element('button', {className: 'activity-count-button', text: '−', attributes: {type: 'button', 'aria-label': 'Helferzahl verringern'}});
                const increase = element('button', {className: 'activity-count-button', text: '+', attributes: {type: 'button', 'aria-label': 'Helferzahl erhöhen'}});
                const setCount = (value) => {
                    const normalized = Math.min(999, Math.max(1, value || 1));
                    count.value = String(normalized);
                    assignment.requiredHelpers = normalized;
                };
                count.addEventListener('input', () => setCount(Number.parseInt(count.value, 10)));
                count.addEventListener('blur', () => setCount(Number.parseInt(count.value, 10)));
                decrease.addEventListener('click', () => setCount(Number.parseInt(count.value, 10) - 1));
                increase.addEventListener('click', () => setCount(Number.parseInt(count.value, 10) + 1));
                const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', title: 'Zuordnung entfernen', 'aria-label': 'Zuordnung entfernen'}});
                remove.addEventListener('click', () => {
                    block.eventActivities.splice(assignmentIndex, 1);
                    renderAssignments();
                });
                return element('div', {className: 'event-activity-editor-row', children: [
                    select,
                    element('div', {className: 'activity-count-control', children: [decrease, count, increase]}),
                    remove,
                ]});
            }));
        };
        const addActivity = element('button', {className: 'secondary-button', text: '＋ Aktivität zuordnen', attributes: {type: 'button'}});
        addActivity.addEventListener('click', () => {
            const available = (handlers.activities || []).find((activity) => activity.active && !block.eventActivities.some((item) => item.activityId === activity.id));
            if (!available) {
                toast('Keine weitere aktive Aktivität verfügbar.', 'error');
                return;
            }
            block.eventActivities.push({activityId: available.id, requiredHelpers: 1});
            renderAssignments();
        });
        renderAssignments();
        const activityEditor = element('fieldset', {className: 'event-activity-editor', children: [
            element('legend', {text: 'Aktivitäten für die Helferanmeldung'}),
            element('small', {text: 'Die benötigte Helferzahl gilt nur für diese Veranstaltung.'}),
            element('div', {className: 'event-activity-editor-head', children: [
                element('strong', {text: 'Aktivität'}),
                element('strong', {text: 'Benötigt'}),
            ]}),
            activityList,
            addActivity,
        ]});
        const helpConfiguration = element('div', {className: 'event-help-configuration', children: [helpLabel, activityEditor]});
        helpConfiguration.hidden = !helpEnabled.checked;
        helpEnabled.addEventListener('change', () => {
            block.eventHelpEnabled = helpEnabled.checked;
            if (helpEnabled.checked && !block.eventIdentifier) block.eventIdentifier = crypto.randomUUID();
            helpConfiguration.hidden = !helpEnabled.checked;
        });
        card.append(helpConfiguration);

        block.eventCallToActions = Array.isArray(block.eventCallToActions) ? block.eventCallToActions : [];
        const actionList = element('div', {className: 'event-call-action-editor-list'});
        const renderActions = () => {
            actionList.replaceChildren(...block.eventCallToActions.map((action, actionIndex) => {
                const label = field('Button-Beschriftung', `block-event-action-label-${index}-${actionIndex}`, action.label || 'Mehr erfahren');
                const labelInput = label.querySelector('input');
                labelInput.maxLength = 80;
                labelInput.required = true;
                labelInput.addEventListener('input', () => action.label = labelInput.value);

                const targetType = element('select', {attributes: {'aria-label': 'Art des Linkziels'}, children: [
                    element('option', {text: 'URL verlinken', attributes: {value: 'url'}}),
                    element('option', {text: 'CMS-Seite verlinken', attributes: {value: 'page'}}),
                ]});
                targetType.value = action.pageId ? 'page' : 'url';
                const targetField = element('div', {className: 'event-call-action-target'});
                const renderTarget = () => {
                    if (targetType.value === 'page') {
                        const pageSelect = element('select', {attributes: {'aria-label': 'Verlinkte CMS-Seite'}});
                        pageSelect.append(element('option', {text: 'Seite auswählen …', attributes: {value: ''}}));
                        flattenPageTree(buildPageTree(handlers.pages || [])).forEach(({page: candidate, depth}) => {
                            if (candidate.id === handlers.currentPageId) return;
                            pageSelect.append(element('option', {
                                text: `${'— '.repeat(depth)}${candidate.title}${candidate.visible ? '' : ' (ausgeblendet)'}`,
                                attributes: {value: candidate.id},
                            }));
                        });
                        pageSelect.value = action.pageId || '';
                        pageSelect.required = true;
                        pageSelect.addEventListener('change', () => action.pageId = pageSelect.value || null);
                        targetField.replaceChildren(element('label', {className: 'field', children: [element('span', {text: 'Verlinkte Seite'}), pageSelect]}));
                        return;
                    }
                    const url = field('URL', `block-event-action-url-${index}-${actionIndex}`, action.url || '/');
                    const urlInput = url.querySelector('input');
                    urlInput.maxLength = 2048;
                    urlInput.required = true;
                    urlInput.addEventListener('input', () => action.url = urlInput.value);
                    targetField.replaceChildren(url);
                };
                targetType.addEventListener('change', () => {
                    if (targetType.value === 'page') {
                        action.url = null;
                    } else {
                        action.pageId = null;
                        action.url = '/';
                    }
                    renderTarget();
                });
                renderTarget();
                const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', title: 'Aktionsbutton entfernen', 'aria-label': 'Aktionsbutton entfernen'}});
                remove.addEventListener('click', async () => {
                    const confirmed = await confirmAction('Aktionsbutton entfernen?', 'Der zusätzliche Aktionsbutton wird aus dieser Veranstaltung entfernt.', 'Entfernen');
                    if (!confirmed) return;
                    block.eventCallToActions.splice(actionIndex, 1);
                    renderActions();
                });
                return element('div', {className: 'event-call-action-editor-row', children: [
                    label,
                    element('label', {className: 'field', children: [element('span', {text: 'Linkziel'}), targetType]}),
                    targetField,
                    remove,
                ]});
            }));
        };
        const addAction = element('button', {className: 'secondary-button', text: '＋ Aktionsbutton hinzufügen', attributes: {type: 'button'}});
        addAction.addEventListener('click', () => {
            block.eventCallToActions.push({label: 'Mehr erfahren', url: '/', pageId: null});
            renderActions();
            actionList.lastElementChild?.querySelector('input')?.focus();
        });
        renderActions();
        card.append(element('fieldset', {className: 'event-call-action-editor', children: [
            element('legend', {text: 'Weitere Aktionsbuttons'}),
            element('small', {text: 'Optional können weitere Buttons auf eine URL oder eine CMS-Seite verweisen.'}),
            actionList,
            addAction,
        ]}));
    } else if (block.type === 'page_teaser') {
        const select = element('select', {attributes: {id: 'block-page-teaser-' + index}});
        select.append(element('option', {text: 'Zielseite auswählen …', attributes: {value: ''}}));
        flattenPageTree(buildPageTree(handlers.pages || [])).forEach(({page: candidate, depth}) => {
            if (candidate.id === handlers.currentPageId) return;
            select.append(element('option', {
                text: `${'— '.repeat(depth)}${candidate.title}${candidate.visible ? '' : ' (ausgeblendet)'}`,
                attributes: {value: candidate.id},
            }));
        });
        select.value = block.embeddedPageId || '';
        select.addEventListener('change', () => block.embeddedPageId = select.value || null);
        const linkLabel = field('Beschriftung des Links', 'block-page-teaser-label-' + index, block.linkLabel || 'Mehr erfahren');
        block.linkLabel = linkLabel.querySelector('input').value;
        linkLabel.querySelector('input').addEventListener('input', (event) => block.linkLabel = event.target.value || 'Mehr erfahren');
        card.append(
            element('label', {className: 'field', children: [
                element('span', {text: 'Verlinkte Unterseite'}),
                select,
                element('small', {text: 'Titel und Link werden automatisch aus der ausgewählten Seite übernommen.'}),
            ]}),
            element('div', {className: 'field', children: [
                element('span', {text: 'Teasertext'}),
                richTextEditor(block, index + '-page-teaser', null, 'Teasertext'),
            ]}),
            linkLabel,
        );
    } else if (block.type === 'embedded_page') {
        const select = element('select', {attributes: {id: 'block-page-' + index}});
        select.append(element('option', {text: 'Seite auswählen …', attributes: {value: ''}}));
        flattenPageTree(buildPageTree(handlers.pages || [])).forEach(({page: candidate, depth}) => {
            if (candidate.id === handlers.currentPageId) return;
            select.append(element('option', {
                text: `${'— '.repeat(depth)}${candidate.title}${candidate.visible ? '' : ' (ausgeblendet)'}`,
                attributes: {value: candidate.id},
            }));
        });
        select.value = block.embeddedPageId || '';
        select.addEventListener('change', () => block.embeddedPageId = select.value || null);
        card.append(element('label', {className: 'field', children: [
            element('span', {text: 'Einzubettende Seite'}),
            select,
            element('small', {text: 'Im Frontend werden nur veröffentlichte und sichtbare Zielseiten ausgegeben.'}),
        ]}));
    } else if (block.type === 'event_reference') {
        const select = element('select', {attributes: {id: 'block-event-reference-' + index}});
        select.append(element('option', {text: 'Veranstaltung auswählen …', attributes: {value: ''}}));
        const selectedValue = block.embeddedPageId && block.eventIdentifier
            ? `${block.embeddedPageId}::${block.eventIdentifier}`
            : '';
        let selectionAvailable = selectedValue === '';

        flattenPageTree(buildPageTree(handlers.pages || [])).forEach(({page: candidate}) => {
            if (candidate.id === handlers.currentPageId) return;
            (candidate.blocks || []).filter((candidateBlock) => candidateBlock.type === 'event' && candidateBlock.eventIdentifier).forEach((event) => {
                const value = `${candidate.id}::${event.eventIdentifier}`;
                const date = event.eventDate
                    ? new Intl.DateTimeFormat('de-DE').format(new Date(`${event.eventDate}T00:00:00`))
                    : 'Ohne Datum';
                select.append(element('option', {
                    text: `${date} · ${event.eventTitle || 'Veranstaltung'} — ${candidate.title}${candidate.visible ? '' : ' (Seite ausgeblendet)'}`,
                    attributes: {value},
                }));
                if (value === selectedValue) selectionAvailable = true;
            });
        });
        if (!selectionAvailable) {
            select.append(element('option', {
                text: 'Ausgewählte Veranstaltung ist nicht mehr verfügbar',
                attributes: {value: selectedValue},
            }));
        }
        select.value = selectedValue;
        select.addEventListener('change', () => {
            const separator = select.value.indexOf('::');
            block.embeddedPageId = separator < 0 ? null : select.value.slice(0, separator);
            block.eventIdentifier = separator < 0 ? null : select.value.slice(separator + 2);
        });
        card.append(element('label', {className: 'field', children: [
            element('span', {text: 'Einzubettende Veranstaltung'}),
            select,
            element('small', {text: 'Datum, Uhrzeit, Bild, Text und Helferanmeldung werden aus der veröffentlichten Veranstaltung übernommen.'}),
        ]}));
    } else if (block.type === 'extension') {
        const select = element('select', {attributes: {id: 'block-extension-' + index}, children: [
            element('option', {text: 'Mitgliedsantrag', attributes: {value: 'membership_application'}}),
            element('option', {text: 'Veranstaltungen: aktuelles Jahr', attributes: {value: 'events_current_year'}}),
            element('option', {text: 'Arbeitseinsätze: aktuelles Jahr', attributes: {value: 'work_assignments_current_year'}}),
            element('option', {text: 'Veranstaltung: nächste', attributes: {value: 'next_event'}}),
            element('option', {text: 'Arbeitseinsatz: nächste', attributes: {value: 'next_work_assignment'}}),
            element('option', {text: 'Veranstaltung/Arbeitseinsatz: nächste', attributes: {value: 'next_event_or_work_assignment'}}),
        ]});
        select.value = block.extensionKey || 'membership_application';
        block.extensionKey = select.value;
        select.addEventListener('change', () => block.extensionKey = select.value);
        card.append(element('label', {className: 'field', children: [
            element('span', {text: 'Seitenerweiterung'}),
            select,
            element('small', {text: 'Rendert das Beitrittsformular im Frontend. Eingegangene Anträge erscheinen im Bereich „Mitgliedsanträge“.'}),
        ]}));
    } else if (block.type === 'image') {
        block.content = '';
    } else {
        const contentLabel = block.type === 'custom_html' ? 'HTML (wird sicher bereinigt)' : 'Inhalt';
        const usesRichText = block.type !== 'custom_html';
        const content = usesRichText
            ? richTextEditor(block, index)
            : field(contentLabel, 'block-content-' + index, block.content, 'textarea');
        if (!usesRichText) {
            content.querySelector('textarea').addEventListener('input', (event) => block.content = event.target.value);
        }
        card.append(content);
    }

    if (block.type === 'image' || block.type === 'image_text' || block.type === 'page_teaser' || block.type === 'event' || block.type === 'event_reference') {
        const optionalMedia = block.type === 'page_teaser' || block.type === 'event' || block.type === 'event_reference';
        const media = field(optionalMedia ? 'Bild-URL (optional)' : 'Bild-URL', 'block-media-' + index, block.mediaUrl || '');
        const alt = field('Alternativtext (optional; leer = dekorativ)', 'block-alt-' + index, block.mediaAlt || '');
        const source = field('Bildquelle (optional)', 'block-source-' + index, block.mediaSource || '');
        const mediaInput = media.querySelector('input');
        const sourceInput = source.querySelector('input');
        let storedSource = block.mediaSource || null;
        mediaInput.addEventListener('input', (event) => block.mediaUrl = event.target.value || null);
        alt.querySelector('input').addEventListener('input', (event) => block.mediaAlt = event.target.value || null);
        sourceInput.setAttribute('maxlength', '300');
        sourceInput.addEventListener('input', (event) => block.mediaSource = event.target.value || null);
        sourceInput.addEventListener('blur', async () => {
            const normalizedSource = sourceInput.value.trim() || null;
            if (!block.mediaUrl?.startsWith('/uploads/media/') || normalizedSource === storedSource) return;
            try {
                const updated = await request('/api/admin/v1/media/images/source', {
                    method: 'PATCH',
                    body: JSON.stringify({url: block.mediaUrl, source: normalizedSource}),
                });
                storedSource = updated.source;
                block.mediaSource = updated.source;
                sourceInput.value = updated.source || '';
                toast('Die Bildquelle wurde in der Medienbibliothek aktualisiert.');
            } catch (error) {
                toast(error.message, 'error');
            }
        });
        const uploadInput = element('input', {attributes: {type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', hidden: 'hidden'}});
        const uploadButton = element('button', {className: 'secondary-button', text: 'Bild hochladen', attributes: {type: 'button'}});
        const selectButton = element('button', {className: 'secondary-button', text: 'Bild auswählen', attributes: {type: 'button'}});
        const uploadMessage = element('small', {className: 'upload-message', attributes: {'aria-live': 'polite'}});
        uploadButton.addEventListener('click', () => uploadInput.click());
        selectButton.addEventListener('click', () => openImagePicker((image) => {
            block.mediaUrl = image.url;
            block.mediaSource = image.source || null;
            mediaInput.value = image.url;
            sourceInput.value = image.source || '';
            storedSource = image.source || null;
            uploadMessage.textContent = `${image.originalName} wurde ausgewählt.`;
            toast(`${image.originalName} wurde ausgewählt.`);
        }));
        uploadInput.addEventListener('change', async () => {
            const image = uploadInput.files?.[0];
            if (!image) return;
            const body = new FormData();
            body.append('image', image);
            body.append('source', sourceInput.value.trim());
            uploadButton.disabled = true;
            uploadMessage.textContent = 'Bild wird hochgeladen …';
            try {
                const stored = await request('/api/admin/v1/media/images', {method: 'POST', body});
                block.mediaUrl = stored.url;
                block.mediaSource = stored.source || null;
                mediaInput.value = stored.url;
                sourceInput.value = stored.source || '';
                storedSource = stored.source || null;
                uploadMessage.textContent = `${stored.originalName} wurde hochgeladen (${stored.width} × ${stored.height} px).`;
                toast(`${stored.originalName} wurde hochgeladen.`);
            } catch (error) {
                uploadMessage.textContent = error.message;
                toast(error.message, 'error');
            } finally {
                uploadButton.disabled = false;
                uploadInput.value = '';
            }
        });
        card.append(
            element('div', {className: 'media-input-row', children: [media, selectButton, uploadButton, uploadInput]}),
            uploadMessage,
            alt,
            source,
            element('small', {text: 'Bei Bibliotheksbildern wird diese Quelle gespeichert und bei jeder späteren Auswahl automatisch übernommen.'}),
            ...(block.type === 'event_reference' ? [element('small', {text: 'Ohne eigenes Bild wird das Bild der ausgewählten Veranstaltung verwendet.'})] : []),
        );
    }
    if (block.type === 'image_text' || block.type === 'page_teaser') {
        if (block.type === 'image_text') {
            const imageLink = field('Linkziel des Bildes (optional)', 'block-image-link-' + index, block.linkUrl || '', 'url');
            imageLink.querySelector('input').addEventListener('input', (event) => block.linkUrl = event.target.value || null);
            card.append(imageLink);
        }

        const layout = element('select', {attributes: {id: 'block-layout-' + index}});
        layout.append(
            element('option', {text: 'Bild links, Text rechts', attributes: {value: 'image_left'}}),
            element('option', {text: 'Text links, Bild rechts', attributes: {value: 'image_right'}}),
        );
        layout.value = block.layout || 'image_left';
        block.layout = layout.value;
        layout.addEventListener('change', () => block.layout = layout.value);
        card.append(element('label', {className: 'field', children: [element('span', {text: 'Anordnung'}), layout]}));

        const width = field('Bildbreite in Prozent', 'block-width-' + index, block.imageWidthPercent || 50, 'number');
        const widthInput = width.querySelector('input');
        widthInput.setAttribute('min', '20');
        widthInput.setAttribute('max', '80');
        widthInput.setAttribute('step', '5');
        block.imageWidthPercent = Number(widthInput.value);
        widthInput.addEventListener('input', () => block.imageWidthPercent = Number(widthInput.value));

        const optionField = (label, name, options, selected) => {
            const select = element('select', {attributes: {id: name}});
            options.forEach(([value, text]) => select.append(element('option', {text, attributes: {value}})));
            select.value = selected;
            return {field: element('label', {className: 'field', children: [element('span', {text: label}), select]}), select};
        };
        const vertical = optionField('Text vertikal', 'block-vertical-' + index, [
            ['top', 'Oben beginnen'], ['center', 'Vertikal zentriert'], ['bottom', 'Unten ausrichten'],
        ], block.verticalAlignment || 'center');
        const horizontal = optionField('Text horizontal', 'block-horizontal-' + index, [
            ['left', 'Linksbündig'], ['center', 'Zentriert'], ['right', 'Rechtsbündig'],
        ], block.textAlignment || 'left');
        const fit = optionField('Bilddarstellung', 'block-fit-' + index, [
            ['cover', 'Fläche ausfüllen / zuschneiden'], ['contain', 'Vollständig anzeigen'],
        ], block.imageFit || 'cover');
        block.verticalAlignment = vertical.select.value;
        block.textAlignment = horizontal.select.value;
        block.imageFit = fit.select.value;
        vertical.select.addEventListener('change', () => block.verticalAlignment = vertical.select.value);
        horizontal.select.addEventListener('change', () => block.textAlignment = horizontal.select.value);
        fit.select.addEventListener('change', () => block.imageFit = fit.select.value);
        card.append(element('div', {className: 'layout-options', children: [width, vertical.field, horizontal.field, fit.field]}));
    }
    if (block.type === 'event_reference') {
        const layout = element('select', {attributes: {id: 'block-event-reference-layout-' + index}, children: [
            element('option', {text: 'Bild links, Veranstaltung rechts', attributes: {value: 'image_left'}}),
            element('option', {text: 'Veranstaltung links, Bild rechts', attributes: {value: 'image_right'}}),
            element('option', {text: 'Bild oben und zentriert', attributes: {value: 'image_top'}}),
        ]});
        layout.value = ['image_left', 'image_right', 'image_top'].includes(block.layout) ? block.layout : 'image_left';
        block.layout = layout.value;
        card.append(element('label', {className: 'field', children: [element('span', {text: 'Anordnung'}), layout]}));

        const width = field('Bildbreite in Prozent', 'block-event-reference-width-' + index, block.imageWidthPercent || 50, 'number');
        const widthInput = width.querySelector('input');
        widthInput.setAttribute('min', '20');
        widthInput.setAttribute('max', layout.value === 'image_top' ? '100' : '80');
        widthInput.setAttribute('step', '5');
        block.imageWidthPercent = Number(widthInput.value);
        widthInput.addEventListener('input', () => block.imageWidthPercent = Number(widthInput.value));
        layout.addEventListener('change', () => {
            block.layout = layout.value;
            widthInput.max = layout.value === 'image_top' ? '100' : '80';
            if (layout.value !== 'image_top' && Number(widthInput.value) > 80) {
                widthInput.value = '80';
                block.imageWidthPercent = 80;
            }
        });

        const optionField = (label, name, options, selected) => {
            const select = element('select', {attributes: {id: name}});
            options.forEach(([value, text]) => select.append(element('option', {text, attributes: {value}})));
            select.value = selected;
            return {field: element('label', {className: 'field', children: [element('span', {text: label}), select]}), select};
        };
        const vertical = optionField('Inhalt vertikal', 'block-event-reference-vertical-' + index, [
            ['top', 'Oben beginnen'], ['center', 'Vertikal zentriert'], ['bottom', 'Unten ausrichten'],
        ], block.verticalAlignment || 'center');
        const horizontal = optionField('Text horizontal', 'block-event-reference-horizontal-' + index, [
            ['left', 'Linksbündig'], ['center', 'Zentriert'], ['right', 'Rechtsbündig'],
        ], block.textAlignment || 'left');
        const fit = optionField('Bilddarstellung', 'block-event-reference-fit-' + index, [
            ['cover', 'Fläche ausfüllen / zuschneiden'], ['contain', 'Vollständig anzeigen'],
        ], block.imageFit || 'cover');
        block.verticalAlignment = vertical.select.value;
        block.textAlignment = horizontal.select.value;
        block.imageFit = fit.select.value;
        vertical.select.addEventListener('change', () => block.verticalAlignment = vertical.select.value);
        horizontal.select.addEventListener('change', () => block.textAlignment = horizontal.select.value);
        fit.select.addEventListener('change', () => block.imageFit = fit.select.value);
        card.append(element('div', {className: 'layout-options', children: [width, vertical.field, horizontal.field, fit.field]}));
    }
    if (block.type === 'image') {
        const width = field('Bildbreite in Prozent', 'block-width-' + index, block.imageWidthPercent || 100, 'number');
        const widthInput = width.querySelector('input');
        widthInput.setAttribute('min', '20');
        widthInput.setAttribute('max', '100');
        widthInput.setAttribute('step', '5');
        block.imageWidthPercent = Number(widthInput.value);
        widthInput.addEventListener('input', () => block.imageWidthPercent = Number(widthInput.value));

        const alignment = element('select', {attributes: {id: 'block-image-alignment-' + index}, children: [
            element('option', {text: 'Linksbündig', attributes: {value: 'left'}}),
            element('option', {text: 'Zentriert', attributes: {value: 'center'}}),
            element('option', {text: 'Rechtsbündig', attributes: {value: 'right'}}),
        ]});
        alignment.value = ['left', 'center', 'right'].includes(block.layout) ? block.layout : 'center';
        block.layout = alignment.value;
        alignment.addEventListener('change', () => block.layout = alignment.value);
        card.append(element('div', {className: 'layout-options', children: [
            width,
            element('label', {className: 'field', children: [element('span', {text: 'Bildausrichtung'}), alignment]}),
        ]}));
    }
    if (block.type === 'call_to_action') {
        const link = field('Link', 'block-link-' + index, block.linkUrl || '');
        const label = field('Linktext', 'block-label-' + index, block.linkLabel || '');
        link.querySelector('input').addEventListener('input', (event) => block.linkUrl = event.target.value || null);
        label.querySelector('input').addEventListener('input', (event) => block.linkLabel = event.target.value || null);
        card.append(link, label);
    }

    const remove = element('button', {className: 'text-button danger', text: 'Block entfernen', attributes: {type: 'button'}});
    remove.addEventListener('click', async () => {
        const confirmed = await confirmAction(
            'Block entfernen?',
            `Der Block „${BLOCK_TYPES[block.type] || block.type}“ wird aus der Seite entfernt. Die Änderung wird mit dem nächsten Speichern dauerhaft.`,
            'Block entfernen',
        );
        if (!confirmed) return;
        handlers.onRemove();
        toast('Block wurde entfernt.');
    });
    card.append(remove);

    return card;
};

const pagePayload = (form, blocks, page) => {
    const data = new FormData(form);
    return {
        title: data.get('title'),
        slug: data.get('slug'),
        navigationLabel: data.get('navigationLabel'),
        parentId: page && !canManagePageStructure() ? page.parentId : data.get('parentId') || null,
        navigationPosition: page && !canManagePageStructure() ? page.navigationPosition : Number(data.get('navigationPosition')),
        visible: data.get('visible') === 'on',
        showInNavigation: data.get('showInNavigation') === 'on',
        seoTitle: data.get('seoTitle') || null,
        seoDescription: data.get('seoDescription') || null,
        pageId: page?.id || null,
        version: page?.version || 0,
        blocks,
    };
};

const openPagePreview = async (payload, availablePages, currentPageId) => {
    const page = await request('/api/admin/v1/pages/preview', {method: 'POST', body: JSON.stringify(payload)});
    const dialog = element('dialog', {className: 'preview-dialog'});
    const frame = element('div', {className: 'preview-frame desktop'});
    const pagesById = new Map(availablePages.map((availablePage) => [availablePage.id, availablePage]));
    const previewContext = {visited: new Set(currentPageId ? [currentPageId] : []), pagesById, showEmbedErrors: true, isPreview: true};
    const article = renderContentCard(page, previewContext);
    frame.append(
        element('div', {className: 'preview-site-header', children: [
            element('strong', {text: 'Waldbad Borkheide'}),
            element('span', {text: page.navigationLabel}),
        ]}),
        element('main', {className: 'page-shell', children: [
            element('section', {className: 'page-hero', children: [
                element('p', {className: 'eyebrow', text: 'Entwurfsvorschau'}),
                element('h1', {text: page.title}),
                ...(page.seoDescription ? [element('p', {className: 'lead', text: page.seoDescription})] : []),
            ]}),
            article,
        ]}),
    );

    const desktop = element('button', {className: 'editor-tool active', text: 'Desktop', attributes: {type: 'button'}});
    const mobile = element('button', {className: 'editor-tool', text: 'Mobil', attributes: {type: 'button'}});
    const setViewport = (mode) => {
        frame.className = 'preview-frame ' + mode;
        desktop.classList.toggle('active', mode === 'desktop');
        mobile.classList.toggle('active', mode === 'mobile');
    };
    desktop.addEventListener('click', () => setViewport('desktop'));
    mobile.addEventListener('click', () => setViewport('mobile'));
    const close = element('button', {className: 'secondary-button', text: 'Vorschau schließen', attributes: {type: 'button'}});
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(
        element('header', {className: 'preview-toolbar', children: [
            element('strong', {text: 'Seitenvorschau – nicht veröffentlicht'}),
            element('div', {className: 'preview-actions', children: [desktop, mobile, close]}),
        ]}),
        element('div', {className: 'preview-stage', children: [frame]}),
    );
    document.body.append(dialog);
    dialog.showModal();
};

const pageEditor = (page, onSaved, pages = [], initialParentId = null, activities = []) => {
    const blocks = (page?.blocks || []).map((block) => ({...block}));
    const blockList = element('div', {className: 'block-list'});
    let draggedIndex = null;

    const moveBlockToPosition = (from, position) => {
        if (from < 0 || from >= blocks.length || position < 0 || position > blocks.length) return;
        const [block] = blocks.splice(from, 1);
        const adjustedPosition = from < position ? position - 1 : position;
        blocks.splice(adjustedPosition, 0, block);
        draggedIndex = null;
        refreshBlocks();
    };

    const swapBlocks = (from, to) => {
        if (to < 0 || to >= blocks.length) return;
        [blocks[from], blocks[to]] = [blocks[to], blocks[from]];
        refreshBlocks();
    };

    const moveBlockToIndex = (from, to) => {
        if (from < 0 || from >= blocks.length || to < 0 || to >= blocks.length || from === to) return;
        const [block] = blocks.splice(from, 1);
        blocks.splice(to, 0, block);
        draggedIndex = null;
        refreshBlocks();
    };

    const blockInserter = (position) => {
        const inserter = element('div', {className: 'block-inserter'});
        const plus = element('button', {className: 'block-plus', text: '+', attributes: {type: 'button', title: 'Inhalt an dieser Stelle einfügen', 'aria-label': 'Inhalt an dieser Stelle einfügen'}});
        const panel = element('div', {className: 'block-insert-panel'});
        panel.hidden = true;
        const select = element('select', {attributes: {'aria-label': 'Neuer Blocktyp'}});
        Object.entries(BLOCK_TYPES).forEach(([type, label]) => select.append(element('option', {text: label, attributes: {value: type}})));
        const insert = element('button', {className: 'secondary-button', text: 'Einfügen', attributes: {type: 'button'}});
        insert.addEventListener('click', () => {
            blocks.splice(position, 0, createBlock(select.value));
            refreshBlocks();
        });
        plus.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            plus.setAttribute('aria-expanded', String(!panel.hidden));
            if (!panel.hidden) select.focus();
        });
        inserter.addEventListener('dragover', (event) => {
            event.preventDefault();
            inserter.classList.add('drag-over');
        });
        inserter.addEventListener('dragleave', () => inserter.classList.remove('drag-over'));
        inserter.addEventListener('drop', (event) => {
            event.preventDefault();
            inserter.classList.remove('drag-over');
            if (draggedIndex !== null) moveBlockToPosition(draggedIndex, position);
        });
        panel.append(select, insert);
        inserter.append(plus, panel);
        return inserter;
    };

    const refreshBlocks = () => {
        const children = [];
        blocks.forEach((block, index) => {
            children.push(blockInserter(index));
            children.push(blockEditor(block, index, {
                lastIndex: blocks.length - 1,
                pages,
                activities,
                currentPageId: page?.id || null,
                onRemove: () => {
                    blocks.splice(index, 1);
                    refreshBlocks();
                },
                onMove: swapBlocks,
                onDragStart: (dragIndex) => draggedIndex = dragIndex,
                onDragEnd: () => {
                    draggedIndex = null;
                    blockList.querySelectorAll('.drag-over').forEach((node) => node.classList.remove('drag-over'));
                },
                onDrop: (position) => {
                    if (draggedIndex !== null) moveBlockToIndex(draggedIndex, position);
                },
            }));
        });
        children.push(blockInserter(blocks.length));
        blockList.replaceChildren(...children);
    };

    const message = element('p', {className: 'form-message', attributes: {'aria-live': 'polite'}});
    const runStatusAction = async (action, saveFirst = false) => {
        try {
            if (saveFirst) {
                await request('/api/admin/v1/pages/' + page.id, {
                    method: 'PUT',
                    body: JSON.stringify(pagePayload(form, blocks, page)),
                });
            }
            await request(`/api/admin/v1/pages/${page.id}/${action}`, {method: 'POST'});
            const messages = {
                'request-review': 'Seite wurde gespeichert und zur Prüfung eingereicht.',
                publish: 'Seite wurde gespeichert und veröffentlicht.',
                unpublish: 'Seite wurde zurückgezogen.',
            };
            toast(messages[action] || 'Status wurde aktualisiert.');
            await onSaved();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
    };
    const statusActions = element('div', {className: 'status-actions'});
    if (page && canEditPages(page.id) && page.status === 'draft') {
        const review = element('button', {className: 'secondary-button', text: 'Zur Prüfung', attributes: {type: 'button'}});
        review.addEventListener('click', () => runStatusAction('request-review', true));
        statusActions.append(review);
    }
    if (canPublishPages(page?.id || null) && page?.status !== 'archived') {
        const publish = element('button', {className: 'button', text: 'Veröffentlichen', attributes: {type: 'button'}});
        publish.addEventListener('click', () => page ? runStatusAction('publish', true) : savePage(true));
        statusActions.append(publish);
    }
    if (page && canPublishPages(page.id) && page.publishedAt && page.status !== 'archived') {
        const unpublish = element('button', {className: 'secondary-button', text: 'Zurückziehen', attributes: {type: 'button'}});
        unpublish.addEventListener('click', () => runStatusAction('unpublish'));
        statusActions.append(unpublish);
    }

    const previewButton = element('button', {className: 'secondary-button', text: 'Vorschau', attributes: {type: 'button'}});
    previewButton.addEventListener('click', async () => {
        try {
            await openPagePreview(pagePayload(form, blocks, page), pages, page?.id || null);
            message.textContent = '';
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
    });
    const saveButton = element('button', {className: 'button', text: 'Entwurf speichern', attributes: {type: 'submit'}});
    if (!canEditPages(page?.id || null) || page?.status === 'archived') saveButton.disabled = true;
    const form = element('form', {
        className: 'editor-form',
        children: [
            element('div', {className: 'editor-heading', children: [
                element('div', {children: [
                    element('p', {
                        className: 'eyebrow',
                        text: page
                            ? `Status: ${page.status}${page.publishedAt && page.status !== 'published' ? ' · letzte Version online' : ''}`
                            : 'Neue Seite',
                    }),
                    element('h2', {text: page?.title || 'Seite anlegen'}),
                ]}),
                element('div', {className: 'editor-actions', children: [statusActions, previewButton, saveButton]}),
            ]}),
            element('div', {className: 'form-grid', children: [
                field('Titel', 'title', page?.title),
                field('Slug', 'slug', page?.slug),
                field('Navigation', 'navigationLabel', page?.navigationLabel),
                field('Position', 'navigationPosition', page?.navigationPosition || 0, 'number'),
                parentPageField(pages, page, initialParentId),
                field('SEO-Titel', 'seoTitle', page?.seoTitle),
                field('SEO-Beschreibung', 'seoDescription', page?.seoDescription, 'textarea'),
            ]}),
            element('label', {className: 'check-field', children: [
                element('input', {attributes: {name: 'visible', type: 'checkbox'}}),
                element('span', {text: 'Im Frontend sichtbar'}),
            ]}),
            element('label', {className: 'check-field', children: [
                element('input', {attributes: {name: 'showInNavigation', type: 'checkbox'}}),
                element('span', {text: 'In Navigation anzeigen'}),
            ]}),
            element('p', {className: 'block-help', text: 'Mit + fügst du Inhalte an der gewünschten Stelle ein. Blöcke lassen sich ziehen oder mit den Pfeilen verschieben.'}),
            blockList,
            message,
        ],
    });
    const titleInput = form.querySelector('[name="title"]');
    const slugInput = form.querySelector('[name="slug"]');
    const parentInput = form.querySelector('[name="parentId"]');
    const navigationLabelInput = form.querySelector('[name="navigationLabel"]');
    const seoTitleInput = form.querySelector('[name="seoTitle"]');
    const initialAutomaticSlug = hierarchicalSlug(
        page?.title || '',
        page?.parentId || initialParentId || '',
        pages,
    );
    let updateSlugAutomatically = !page || page.slug === initialAutomaticSlug;
    let updateNavigationAutomatically = !page || page.navigationLabel === page.title;
    let updateSeoTitleAutomatically = !page || !page.seoTitle || page.seoTitle === page.title;
    const refreshAutomaticSlug = () => {
        if (updateSlugAutomatically) {
            slugInput.value = hierarchicalSlug(titleInput.value, parentInput.value, pages);
        }
    };
    if (!page) slugInput.readOnly = true;
    slugInput.addEventListener('input', () => updateSlugAutomatically = false);
    parentInput.addEventListener('change', refreshAutomaticSlug);
    navigationLabelInput.addEventListener('input', () => updateNavigationAutomatically = false);
    seoTitleInput.addEventListener('input', () => updateSeoTitleAutomatically = false);
    titleInput.addEventListener('input', () => {
        refreshAutomaticSlug();
        if (updateNavigationAutomatically) navigationLabelInput.value = titleInput.value.trim();
        if (updateSeoTitleAutomatically) seoTitleInput.value = titleInput.value.trim();
    });
    form.querySelector('[name="visible"]').checked = page?.visible ?? true;
    form.querySelector('[name="showInNavigation"]').checked = page?.showInNavigation ?? true;
    const savePage = async (publishAfterSave = false) => {
        const payload = pagePayload(form, blocks, page);
        try {
            const savedPage = await request(page ? '/api/admin/v1/pages/' + page.id : '/api/admin/v1/pages', {
                method: page ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });
            if (publishAfterSave) {
                await request(`/api/admin/v1/pages/${savedPage.id}/publish`, {method: 'POST'});
                message.textContent = 'Seite veröffentlicht.';
                toast('Seite wurde gespeichert und veröffentlicht.');
            } else {
                message.textContent = 'Entwurf gespeichert.';
                toast(page ? 'Entwurf wurde gespeichert.' : 'Seite wurde angelegt.');
            }
            await onSaved();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
    };
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await savePage();
    });
    refreshBlocks();

    return form;
};

const renderAdmin = async () => {
    let session;
    try {
        session = await request('/api/auth/v1/me');
    } catch {
        renderLogin();
        return;
    }

    csrfToken = session.csrfToken;
    currentRoles = session.user.roles;
    currentModuleAccess = session.user.moduleAccess || {};
    currentPageAccess = session.user.pageAccess ?? null;
    const workspace = element('section', {className: 'admin-workspace'});
    const sidebarTitle = element('h1', {text: 'Redaktion'});
    const menu = element('nav', {className: 'admin-menu', attributes: {id: 'admin-navigation-menu', 'aria-label': 'Redaktionsbereiche'}});
    const sidebar = element('aside', {className: 'admin-sidebar', attributes: {id: 'admin-navigation'}, children: [
        sidebarTitle,
        menu,
    ]});
    const adminLayout = element('main', {className: 'admin-layout', children: [sidebar, workspace]});
    const navigationToggle = element('button', {
        className: 'admin-nav-toggle',
        attributes: {
            type: 'button',
            'aria-controls': 'admin-navigation',
            'aria-expanded': 'false',
            'aria-label': 'Redaktionsnavigation öffnen',
        },
        children: [
            element('span', {className: 'admin-nav-toggle-bar'}),
            element('span', {className: 'admin-nav-toggle-bar'}),
            element('span', {className: 'admin-nav-toggle-bar'}),
        ],
    });
    const closeAdminNavigation = () => {
        adminLayout.classList.remove('admin-nav-open');
        navigationToggle.setAttribute('aria-expanded', 'false');
        navigationToggle.setAttribute('aria-label', 'Redaktionsnavigation öffnen');
    };
    navigationToggle.addEventListener('click', () => {
        const open = adminLayout.classList.toggle('admin-nav-open');
        navigationToggle.setAttribute('aria-expanded', String(open));
        navigationToggle.setAttribute('aria-label', open ? 'Redaktionsnavigation schließen' : 'Redaktionsnavigation öffnen');
    });
    workspace.addEventListener('click', closeAdminNavigation);

    const adminPath = (...segments) => `/admin/${segments.filter(Boolean).map(encodeURIComponent).join('/')}`;
    const currentAdminSegments = () => window.location.pathname
        .replace(/^\/admin\/?/, '')
        .split('/')
        .filter(Boolean)
        .map((segment) => decodeURIComponent(segment));
    const setAdminPath = (segments, replace = false) => {
        const path = adminPath(...segments);
        if (window.location.pathname === path) return;
        window.history[replace ? 'replaceState' : 'pushState']({}, '', path);
    };
    const initialAdminSegments = currentAdminSegments();
    const membershipTabsBySlug = {
        dashboard: 'dashboard',
        mitglieder: 'members',
        beitragssaetze: 'rates',
        antraege: 'applications',
        nachrichten: 'messages',
    };
    const membershipSlugsByTab = Object.fromEntries(Object.entries(membershipTabsBySlug).map(([slug, tab]) => [tab, slug]));
    const eventTabsBySlug = {termine: 'events', helfer: 'helpers', aktivitaeten: 'activities'};
    const eventSlugsByTab = Object.fromEntries(Object.entries(eventTabsBySlug).map(([slug, tab]) => [tab, slug]));
    const contactTabsBySlug = {kontaktanfragen: 'contact', gaestebuch: 'guestbook'};
    const contactSlugsByTab = Object.fromEntries(Object.entries(contactTabsBySlug).map(([slug, tab]) => [tab, slug]));
    const settingsTabsBySlug = {benutzer: 'users', 'pin-schutz': 'pin', 'e-mail': 'email'};
    const settingsSlugsByTab = Object.fromEntries(Object.entries(settingsTabsBySlug).map(([slug, tab]) => [tab, slug]));
    const emailTabsBySlug = {verbindung: 'connection', vorlagen: 'templates', signaturen: 'signatures'};
    const emailSlugsByTab = Object.fromEntries(Object.entries(emailTabsBySlug).map(([slug, tab]) => [tab, slug]));
    let activeMembershipTab = initialAdminSegments[0] === 'mitglieder' ? membershipTabsBySlug[initialAdminSegments[1]] ?? null : null;
    let activeEventTab = initialAdminSegments[0] === 'veranstaltungen' ? eventTabsBySlug[initialAdminSegments[1]] ?? null : null;
    let activeContactTab = initialAdminSegments[0] === 'kontakt-feedback' ? contactTabsBySlug[initialAdminSegments[1]] ?? null : null;
    let activeSettingsTab = initialAdminSegments[0] === 'einstellungen' ? settingsTabsBySlug[initialAdminSegments[1]] ?? null : null;
    let activeEmailSettingsTab = initialAdminSegments[0] === 'einstellungen' && initialAdminSegments[1] === 'e-mail'
        ? emailTabsBySlug[initialAdminSegments[2]] ?? null
        : null;

    const showPages = async () => {
        const [pages, activityData] = await Promise.all([
            request('/api/admin/v1/pages'),
            request('/api/admin/v1/event-activities'),
        ]);
        const activities = activityData.items || [];
        const list = element('div', {className: 'management-list page-tree-panel'});
        const pageById = new Map(pages.items.map((page) => [page.id, page]));
        const pointerDropTargets = new WeakMap();
        let draggedPageId = null;
        const isDescendantOf = (pageId, possibleAncestorId) => {
            let current = pageById.get(pageId);
            while (current?.parentId) {
                if (current.parentId === possibleAncestorId) return true;
                current = pageById.get(current.parentId);
            }

            return false;
        };
        const clearDragState = () => {
            draggedPageId = null;
            list.classList.remove('is-page-dragging');
            list.querySelectorAll('.drag-over, .is-disabled').forEach((node) => node.classList.remove('drag-over', 'is-disabled'));
        };
        const reorderPage = async (parentId, position) => {
            const draggedPage = draggedPageId ? pageById.get(draggedPageId) : null;
            if (!draggedPage || draggedPage.id === parentId || (parentId && isDescendantOf(parentId, draggedPage.id))) {
                clearDragState();
                if (draggedPage) toast('Eine Seite kann nicht in sich selbst oder eine eigene Unterseite verschoben werden.', 'error');
                return;
            }
            const targetSiblings = pages.items
                .filter((candidate) => candidate.parentId === parentId)
                .sort((left, right) => (left.navigationPosition - right.navigationPosition)
                    || left.title.localeCompare(right.title, 'de') || left.id.localeCompare(right.id));
            const sourceIndex = targetSiblings.findIndex((candidate) => candidate.id === draggedPage.id);
            const adjustedPosition = sourceIndex >= 0 && sourceIndex < position ? position - 1 : position;
            if (draggedPage.parentId === parentId && sourceIndex === adjustedPosition) {
                clearDragState();
                return;
            }
            clearDragState();
            try {
                await request(`/api/admin/v1/pages/${draggedPage.id}/position`, {
                    method: 'PUT',
                    body: JSON.stringify({
                        parentId,
                        navigationPosition: adjustedPosition,
                        version: draggedPage.version,
                    }),
                });
                const target = parentId ? `unter „${pageById.get(parentId)?.title || 'Seite'}“` : 'als Hauptseite';
                toast(`„${draggedPage.title}“ wurde ${target} einsortiert.`);
                await showPages();
            } catch (error) {
                toast(error.message, 'error');
                await showPages();
            }
        };
        const pageDropZone = (parentId, position, rootLevel = false) => {
            const zone = element('li', {
                className: 'page-tree-drop-zone',
                attributes: {'aria-hidden': 'true'},
                children: [element('span', {text: rootLevel ? 'Als Hauptseite hier einsortieren' : 'Hier einsortieren'})],
            });
            pointerDropTargets.set(zone, () => reorderPage(parentId, position));

            return zone;
        };
        if (canManagePageStructure()) {
            const create = element('button', {className: 'secondary-button full', text: '＋ Neue Hauptseite', attributes: {type: 'button'}});
            create.addEventListener('click', () => workspace.replaceChildren(pageEditor(null, showPages, pages.items, null, activities)));
            list.append(create);
        }
        const renderTreeNode = (page, siblingIndex, siblings) => {
            const title = element('button', {
                className: 'page-tree-title',
                attributes: {type: 'button', title: `${page.title} bearbeiten`},
                children: [
                    element('span', {className: 'page-icon', text: page.visible ? '▤' : '⊘', attributes: {'aria-hidden': 'true'}}),
                    element('span', {children: [
                        element('strong', {text: page.title}),
                        element('small', {
                            text: `${page.status}${page.publishedAt && page.status !== 'published' ? ' · letzte Version online' : ''}${page.visible ? '' : ' · ausgeblendet'} · /${page.slug}`,
                        }),
                    ]}),
                ],
            });
            title.addEventListener('click', () => workspace.replaceChildren(pageEditor(page, showPages, pages.items, null, activities)));

            const actionDefinitions = [];
            if (canManagePageStructure()) {
                const runPageAction = async (button, url, method, success) => {
                    button.disabled = true;
                    try {
                        await request(url, {method});
                        toast(success);
                        await showPages();
                    } catch (error) {
                        toast(error.message, 'error');
                        button.disabled = false;
                    }
                };
                actionDefinitions.push(
                    {
                        icon: '＋',
                        label: `Unterseite zu ${page.title} hinzufügen`,
                        menuLabel: 'Unterseite hinzufügen',
                        run: () => workspace.replaceChildren(pageEditor(null, showPages, pages.items, page.id, activities)),
                    },
                    {
                        icon: '✎',
                        label: `${page.title} bearbeiten`,
                        menuLabel: 'Bearbeiten',
                        run: () => workspace.replaceChildren(pageEditor(page, showPages, pages.items, null, activities)),
                    },
                    {
                        icon: '⧉',
                        label: `${page.title} duplizieren`,
                        menuLabel: 'Duplizieren',
                        run: (button) => runPageAction(
                            button,
                            `/api/admin/v1/pages/${page.id}/duplicate`,
                            'POST',
                            `„${page.title}“ wurde als ausgeblendeter Entwurf dupliziert.`,
                        ),
                    },
                    {
                        icon: '↑',
                        label: `${page.title} nach oben verschieben`,
                        menuLabel: 'Nach oben verschieben',
                        disabled: siblingIndex === 0,
                        run: (button) => runPageAction(
                            button,
                            `/api/admin/v1/pages/${page.id}/move/up`,
                            'POST',
                            `„${page.title}“ wurde nach oben verschoben.`,
                        ),
                    },
                    {
                        icon: '↓',
                        label: `${page.title} nach unten verschieben`,
                        menuLabel: 'Nach unten verschieben',
                        disabled: siblingIndex === siblings.length - 1,
                        run: (button) => runPageAction(
                            button,
                            `/api/admin/v1/pages/${page.id}/move/down`,
                            'POST',
                            `„${page.title}“ wurde nach unten verschoben.`,
                        ),
                    },
                    {
                        icon: '✕',
                        label: `${page.title} löschen`,
                        menuLabel: 'Löschen',
                        danger: true,
                        run: async (button) => {
                            const confirmed = await confirmAction(
                                `„${page.title}“ löschen?`,
                                'Die Seite und ihre Inhalte werden dauerhaft gelöscht. Seiten mit Unterseiten oder Einbettungen können erst gelöscht werden, nachdem diese Abhängigkeiten entfernt wurden.',
                                'Seite löschen',
                            );
                            if (!confirmed) return;
                            await runPageAction(button, `/api/admin/v1/pages/${page.id}`, 'DELETE', `„${page.title}“ wurde gelöscht.`);
                        },
                    },
                );
            }

            const createActionButton = (definition, mobile = false) => {
                const button = element('button', {
                    className: mobile
                        ? `page-tree-menu-action${definition.danger ? ' danger' : ''}`
                        : `tree-icon-button${definition.danger ? ' danger' : ''}`,
                    text: mobile ? definition.menuLabel : definition.icon,
                    attributes: {type: 'button', title: definition.label, 'aria-label': definition.label},
                });
                button.disabled = definition.disabled === true;
                button.addEventListener('click', () => definition.run(button));

                return button;
            };
            const actionContainers = [];
            if (actionDefinitions.length) {
                const mobileActionMenu = element('details', {
                    className: 'page-tree-action-menu',
                    children: [
                        element('summary', {
                            className: 'page-tree-action-menu-toggle',
                            text: '⋮',
                            attributes: {title: `Aktionen für ${page.title}`, 'aria-label': `Aktionen für ${page.title}`},
                        }),
                        element('div', {
                            className: 'page-tree-action-menu-popover',
                            children: actionDefinitions.map((definition) => createActionButton(definition, true)),
                        }),
                    ],
                });
                mobileActionMenu.querySelectorAll('.page-tree-menu-action').forEach((button) => {
                    button.addEventListener('click', () => mobileActionMenu.removeAttribute('open'));
                });
                mobileActionMenu.addEventListener('toggle', () => {
                    if (!mobileActionMenu.open) return;
                    list.querySelectorAll('.page-tree-action-menu[open]').forEach((menu) => {
                        if (menu !== mobileActionMenu) menu.removeAttribute('open');
                    });
                });
                actionContainers.push(
                    element('div', {
                        className: 'page-tree-actions',
                        children: actionDefinitions.map((definition) => createActionButton(definition)),
                    }),
                    mobileActionMenu,
                );
            }

            const dragHandle = element('div', {
                className: 'page-tree-drag-handle',
                text: '↕',
                attributes: {title: `${page.title} ziehen`, 'aria-label': `${page.title} per Drag-and-drop verschieben`},
            });
            let pointerId = null;
            let pointerTarget = null;
            const updatePointerTarget = (clientX, clientY) => {
                const candidate = document.elementFromPoint(clientX, clientY)?.closest('.page-tree-drop-zone, .page-tree-child-drop-zone');
                const target = candidate && list.contains(candidate) && pointerDropTargets.has(candidate) ? candidate : null;
                if (pointerTarget === target) return;
                pointerTarget?.classList.remove('drag-over');
                pointerTarget = target;
                pointerTarget?.classList.add('drag-over');
            };
            const endPointerDrag = async (event, drop) => {
                if (event.pointerId !== pointerId) return;
                dragHandle.releasePointerCapture?.(event.pointerId);
                const action = drop && pointerTarget ? pointerDropTargets.get(pointerTarget) : null;
                pointerTarget?.classList.remove('drag-over');
                pointerTarget = null;
                pointerId = null;
                item.classList.remove('dragging');
                if (action) await action();
                else clearDragState();
            };
            dragHandle.addEventListener('pointerdown', (event) => {
                if (event.pointerType === 'mouse' && event.button !== 0) return;
                event.preventDefault();
                pointerId = event.pointerId;
                draggedPageId = page.id;
                list.classList.add('is-page-dragging');
                item.classList.add('dragging');
                list.querySelectorAll('.page-tree-child-drop-zone').forEach((zone) => {
                    const parentPageId = zone.dataset.parentPageId;
                    if (parentPageId === page.id || (parentPageId && isDescendantOf(parentPageId, page.id))) {
                        zone.classList.add('is-disabled');
                    }
                });
                dragHandle.setPointerCapture?.(event.pointerId);
            });
            dragHandle.addEventListener('pointermove', (event) => {
                if (event.pointerId !== pointerId) return;
                event.preventDefault();
                updatePointerTarget(event.clientX, event.clientY);
            });
            dragHandle.addEventListener('pointerup', (event) => endPointerDrag(event, true));
            dragHandle.addEventListener('pointercancel', (event) => endPointerDrag(event, false));
            const row = element('div', {
                className: 'page-tree-row',
                children: [...(canManagePageStructure() ? [dragHandle] : []), title, ...actionContainers],
            });
            const item = element('li', {className: `page-tree-node${page.visible ? '' : ' is-hidden'}`, children: [row]});
            const childDropZone = element('div', {
                className: 'page-tree-child-drop-zone',
                attributes: {'aria-hidden': 'true', 'data-parent-page-id': page.id},
                children: [element('span', {text: `Als Unterseite von „${page.title}“ ablegen`})],
            });
            pointerDropTargets.set(childDropZone, () => reorderPage(page.id, page.children.length));
            item.append(childDropZone);
            if (page.children.length) {
                const children = renderTreeLevel(page.children, page.id);
                const toggle = element('button', {className: 'tree-toggle', text: '▾', attributes: {type: 'button', title: 'Unterseiten ein- oder ausblenden', 'aria-label': `Unterseiten von ${page.title} ausblenden`, 'aria-expanded': 'true'}});
                toggle.addEventListener('click', () => {
                    children.hidden = !children.hidden;
                    toggle.textContent = children.hidden ? '▸' : '▾';
                    toggle.setAttribute('aria-expanded', String(!children.hidden));
                    toggle.setAttribute('aria-label', `Unterseiten von ${page.title} ${children.hidden ? 'anzeigen' : 'ausblenden'}`);
                });
                row.prepend(toggle);
                item.append(children);
            } else {
                row.prepend(element('span', {className: 'tree-toggle-placeholder', attributes: {'aria-hidden': 'true'}}));
            }

            return item;
        };
        const renderTreeLevel = (nodes, parentId, rootLevel = false) => {
            const children = [];
            nodes.forEach((node, index) => {
                children.push(pageDropZone(parentId, index, rootLevel), renderTreeNode(node, index, nodes));
            });
            children.push(pageDropZone(parentId, nodes.length, rootLevel));

            return element('ul', {className: rootLevel ? 'page-tree' : 'page-tree-children', children});
        };
        const tree = buildPageTree(pages.items);
        list.append(tree.length
            ? renderTreeLevel(tree, null, true)
            : emptyState('Noch keine Seiten vorhanden.'));
        workspace.replaceChildren(sectionHeading(
            'Seitenstruktur',
            'Seiten am ↕-Griff ziehen, zwischen Seiten sortieren oder auf einer Seite als Untermenü ablegen',
        ), list);
    };

    const showGuestbook = async () => {
        const data = await request('/api/admin/v1/guestbook-entries');
        const pendingEntries = data.items.filter((entry) => entry.status === 'pending');
        const publishedEntries = data.items.filter((entry) => entry.status === 'published');
        const otherEntries = data.items.filter((entry) => !['pending', 'published'].includes(entry.status));
        const archive = (title, entries) => element('details', {className: 'guestbook-archive', children: [
            element('summary', {children: [
                element('strong', {text: title}),
                element('span', {className: 'status-badge', text: String(entries.length)}),
            ]}),
            element('div', {
                className: 'card-list guestbook-archive-list',
                children: entries.map((entry) => moderationCard(entry, showGuestbook)),
            }),
        ]});

        workspace.replaceChildren(
            sectionHeading('Gästebuch', 'Neue Einträge prüfen und moderieren'),
            element('section', {className: 'guestbook-admin', children: [
                element('div', {className: 'guestbook-pending-heading', children: [
                    element('h3', {text: 'Neue Einträge'}),
                    element('span', {className: 'status-badge status-pending', text: String(pendingEntries.length)}),
                ]}),
                element('div', {
                    className: 'card-list',
                    children: pendingEntries.length
                        ? pendingEntries.map((entry) => moderationCard(entry, showGuestbook))
                        : [emptyState('Keine neuen Gästebucheinträge vorhanden.')],
                }),
                ...(publishedEntries.length ? [archive('Veröffentlichte Einträge', publishedEntries)] : []),
                ...(otherEntries.length ? [archive('Abgelehnte Einträge und Spam', otherEntries)] : []),
            ]}),
        );
    };

    const showContact = async () => {
        const data = await request('/api/admin/v1/contact-requests');
        workspace.replaceChildren(sectionHeading('Kontaktanfragen', 'Anfragen bearbeiten und abschließen'), element('div', {
            className: 'card-list', children: data.items.length ? data.items.map((item) => contactCard(item, showContact)) : [emptyState('Keine Kontaktanfragen vorhanden.')],
        }));
    };

    const showMemberMessages = async () => {
        const data = await request('/api/admin/v1/member-messages');
        workspace.replaceChildren(sectionHeading('Mitgliedernachrichten', 'Über „Meine Mitgliedschaft" gesendete Nachrichten bearbeiten und abschließen'), element('div', {
            className: 'card-list', children: data.items.length ? data.items.map((item) => memberMessageCard(item, showMemberMessages)) : [emptyState('Keine Nachrichten vorhanden.')],
        }));
    };

    const showEventHelpers = async () => {
        const data = await request('/api/admin/v1/event-help-requests');
        const saveParticipation = async (requestItem, participated, intervals = []) => {
            await request(`/api/admin/v1/event-help-requests/${requestItem.id}/participation`, {
                method: 'POST',
                body: JSON.stringify({participated, intervals}),
            });
            toast(participated ? 'Teilnahme und Helferstunden wurden gespeichert.' : 'Die Person wurde als nicht teilgenommen markiert.');
            await showEventManagement();
        };
        const openParticipationDialog = (requestItem) => {
            const dialog = element('dialog', {className: 'participation-dialog'});
            const message = formMessage();
            const intervalList = element('div', {className: 'participation-interval-list'});
            const addInterval = element('button', {className: 'secondary-button participation-add-interval', text: '＋ Weiteren Zeitraum hinzufügen', attributes: {type: 'button'}});
            const totalPreview = element('p', {className: 'participation-total-preview', text: 'Gesamtzeit: 0 Stunden'});
            const updateTotalPreview = () => {
                const minutes = [...intervalList.children].reduce((total, row) => {
                    const fromValue = row.querySelector('[data-interval-from]').value;
                    const toValue = row.querySelector('[data-interval-to]').value;
                    if (!/^\d{2}:\d{2}$/.test(fromValue) || !/^\d{2}:\d{2}$/.test(toValue)) return total;
                    const [fromHours, fromMinutes] = fromValue.split(':').map(Number);
                    const [toHours, toMinutes] = toValue.split(':').map(Number);
                    const duration = toHours * 60 + toMinutes - (fromHours * 60 + fromMinutes);
                    return total + Math.max(0, duration);
                }, 0);
                const hours = new Intl.NumberFormat('de-DE', {maximumFractionDigits: 2}).format(minutes / 60);
                totalPreview.textContent = `Gesamtzeit: ${hours} Stunden`;
            };
            const refreshIntervalRows = () => {
                [...intervalList.children].forEach((row) => {
                    row.querySelector('.participation-remove-interval').hidden = intervalList.children.length === 1;
                });
                addInterval.disabled = intervalList.children.length >= 10;
            };
            const appendInterval = (values = {}) => {
                if (intervalList.children.length >= 10) return;
                const rowId = `${requestItem.id}-${Math.random().toString(36).slice(2)}`;
                const from = field('Von', `participation-from-${rowId}`, values.fromTime || '', 'time');
                const to = field('Bis', `participation-to-${rowId}`, values.toTime || '', 'time');
                const fromInput = from.querySelector('input');
                const toInput = to.querySelector('input');
                fromInput.required = true;
                toInput.required = true;
                fromInput.dataset.intervalFrom = 'true';
                toInput.dataset.intervalTo = 'true';
                fromInput.addEventListener('input', updateTotalPreview);
                toInput.addEventListener('input', updateTotalPreview);
                const remove = element('button', {className: 'text-button danger participation-remove-interval', text: 'Zeitraum entfernen', attributes: {type: 'button'}});
                const row = element('section', {className: 'participation-interval-row', children: [
                    element('div', {className: 'form-grid', children: [from, to]}),
                    remove,
                ]});
                remove.addEventListener('click', () => {
                    row.remove();
                    refreshIntervalRows();
                    updateTotalPreview();
                });
                intervalList.append(row);
                refreshIntervalRows();
                updateTotalPreview();
            };
            const existingIntervals = Array.isArray(requestItem.participationIntervals) ? requestItem.participationIntervals : [];
            if (existingIntervals.length) existingIntervals.forEach((interval) => appendInterval(interval));
            else appendInterval({fromTime: requestItem.eventTime || '', toTime: ''});
            addInterval.addEventListener('click', () => appendInterval());
            const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
            const submit = element('button', {className: 'button', text: 'Teilnahme speichern', attributes: {type: 'submit'}});
            const form = element('form', {className: 'participation-form', children: [
                element('p', {className: 'eyebrow', text: 'Teilnahme erfassen'}),
                element('h2', {text: `${requestItem.firstName} ${requestItem.lastName}`}),
                element('p', {text: requestItem.eventTitle}),
                intervalList,
                addInterval,
                totalPreview,
                message,
                element('div', {className: 'confirm-dialog-actions', children: [cancel, submit]}),
            ]});
            cancel.addEventListener('click', () => dialog.close());
            form.addEventListener('submit', async (submitEvent) => {
                submitEvent.preventDefault();
                submit.disabled = true;
                try {
                    const intervals = [...intervalList.children].map((row) => ({
                        fromTime: row.querySelector('[data-interval-from]').value,
                        toTime: row.querySelector('[data-interval-to]').value,
                    }));
                    await saveParticipation(requestItem, true, intervals);
                    dialog.close();
                } catch (error) {
                    message.textContent = error.message;
                    toast(error.message, 'error');
                    submit.disabled = false;
                }
            });
            dialog.addEventListener('close', () => dialog.remove());
            dialog.append(form);
            document.body.append(dialog);
            dialog.showModal();
        };
        // Eigener, schlanker Such-Endpunkt statt der vollständigen Mitgliederliste (siehe
        // `AdminEventHelpRequestController::memberCandidates`) — läuft ohne Mitgliederverwaltung-Recht.
        // Der Dialog deckt zwei Fälle in einem ab: erstmalig verknüpfen bzw. eine bestehende (ggf.
        // falsche) Verknüpfung nachträglich ändern/lösen. Vor-/Nachname der Anmeldung werden dabei
        // automatisch vom ausgewählten Mitglied übernommen (korrigiert so nebenbei einen Tippfehler,
        // der das automatische Matching verhindert haben könnte) — eigene Namensfelder braucht es
        // dafür nicht.
        //
        // `items` ist eine Liste statt einer einzelnen Anmeldung, weil mehrfache Anmeldungen
        // derselben Person zur selben Veranstaltung (siehe `buildParticipantEntries`) zu einer
        // Mitgliedskarte zusammengeführt werden — Verknüpfen/Bearbeiten/Lösen wirkt dann auf alle
        // zusammengeführten Anmeldungen gleichzeitig, damit sie nicht wieder auseinanderfallen.
        const openLinkMemberDialog = (items) => {
            const primary = items[0];
            const dialog = element('dialog', {className: 'confirm-dialog'});
            const message = formMessage();
            let selectedMember = primary.memberId ? {
                id: primary.memberId, firstName: primary.memberFirstName, lastName: primary.memberLastName,
                memberNumber: primary.memberNumber,
            } : null;

            const linkStatus = element('p', {className: 'member-link-status'});
            const removeLink = element('button', {className: 'text-button danger', text: 'Verknüpfung entfernen', attributes: {type: 'button'}});
            const updateLinkStatus = () => {
                linkStatus.textContent = selectedMember
                    ? `Verknüpft mit ${selectedMember.firstName} ${selectedMember.lastName} (${selectedMember.memberNumber})`
                    : 'Nicht mit einem Mitglied verknüpft.';
                removeLink.hidden = !selectedMember;
            };
            removeLink.addEventListener('click', () => {
                selectedMember = null;
                updateLinkStatus();
                results.querySelectorAll('.member-link-candidate.is-selected').forEach((button) => button.classList.remove('is-selected'));
            });
            updateLinkStatus();

            const results = element('div', {className: 'member-link-results'});
            const renderResults = (candidates) => {
                results.replaceChildren(...(candidates.length ? candidates.map((candidate) => {
                    const pick = element('button', {
                        className: `secondary-button member-link-candidate${selectedMember && selectedMember.id === candidate.id ? ' is-selected' : ''}`,
                        attributes: {type: 'button'},
                        children: [
                            element('strong', {text: `${candidate.firstName} ${candidate.lastName} (${candidate.memberNumber})`}),
                            element('small', {text: `${candidate.street} • ${new Date(`${candidate.birthDate}T00:00:00`).toLocaleDateString('de-DE')}`}),
                        ],
                    });
                    pick.addEventListener('click', () => {
                        selectedMember = candidate;
                        updateLinkStatus();
                        results.querySelectorAll('.member-link-candidate').forEach((button) => {
                            button.classList.toggle('is-selected', button === pick);
                        });
                    });

                    return pick;
                }) : [emptyState('Keine Mitglieder gefunden.')]));
            };
            const search = searchField('Name oder Mitgliedsnummer suchen …', '', async (term) => {
                if (term === '') { results.replaceChildren(); return; }
                const data = await request(`/api/admin/v1/event-help-requests/member-candidates?search=${encodeURIComponent(term)}`);
                renderResults(data.items);
            });
            // Enter im Suchfeld soll die Suche auslösen (wie ein Bestätigen/Verlassen des Felds),
            // nicht das ganze Formular abschicken — Speichern (Verknüpfen) erfolgt nur per Klick.
            const searchInput = search.querySelector('input');
            searchInput.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                searchInput.dispatchEvent(new Event('change'));
            });

            const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
            const save = element('button', {className: 'button button-compact', text: 'Verknüpfen', attributes: {type: 'submit'}});
            cancel.addEventListener('click', () => dialog.close());
            dialog.addEventListener('close', () => dialog.remove());
            const form = element('form', {className: 'confirm-dialog-content', children: [
                element('p', {className: 'eyebrow', text: 'Mitglied verknüpfen'}),
                element('h2', {text: `${primary.firstName} ${primary.lastName}`}),
                ...(items.length > 1 ? [element('p', {className: 'member-link-status', text: `${items.length} zusammengeführte Anmeldungen zu dieser Veranstaltung — die Verknüpfung gilt für alle.`})] : []),
                linkStatus,
                element('div', {className: 'member-link-status-actions', children: [removeLink]}),
                search,
                results,
                message,
                element('div', {className: 'confirm-dialog-actions', children: [cancel, save]}),
            ]});
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                save.disabled = true;
                try {
                    await Promise.all(items.map((item) => request(`/api/admin/v1/event-help-requests/${item.id}/member`, {
                        method: 'POST',
                        body: JSON.stringify({
                            memberId: selectedMember ? selectedMember.id : '',
                            firstName: selectedMember ? selectedMember.firstName : item.firstName,
                            lastName: selectedMember ? selectedMember.lastName : item.lastName,
                        }),
                    })));
                    toast(selectedMember ? 'Die Helferanmeldung wurde mit dem Mitglied verknüpft.' : 'Die Verknüpfung wurde entfernt.');
                    dialog.close();
                    await showEventManagement();
                } catch (error) {
                    message.textContent = error.message;
                    toast(error.message, 'error');
                    save.disabled = false;
                }
            });
            dialog.append(form);
            document.body.append(dialog);
            dialog.showModal();
        };
        const participationStatus = (requestItem) => {
            if (requestItem.status === 'participated') {
                const hours = new Intl.NumberFormat('de-DE', {maximumFractionDigits: 2}).format(requestItem.participationMinutes / 60);
                return `Teilgenommen · ${hours} Std.`;
            }
            if (requestItem.status === 'not_participated') return 'Nicht teilgenommen';
            if (requestItem.status === 'resolved') return 'Erledigt';
            return 'Neu';
        };
        const groups = new Map();
        data.items.forEach((item) => {
            if (!groups.has(item.eventIdentifier)) groups.set(item.eventIdentifier, {event: item, requests: []});
            groups.get(item.eventIdentifier).requests.push(item);
        });
        // ✓/⊘ zum Erfassen der Teilnahme — je Anmeldung, unabhängig davon, ob sie einzeln oder als
        // Teil einer zusammengeführten Mitgliedskarte angezeigt wird (siehe `buildParticipantEntries`).
        const buildParticipationActionButtons = (requestItem) => {
            const actionButtons = [];
            if (!canEditModule('event_helpers') || requestItem.status === 'resolved') return actionButtons;
            const participated = element('button', {
                className: 'participant-icon-button participant-icon-button-confirm',
                text: requestItem.status === 'participated' ? '✎' : '✓',
                attributes: {
                    type: 'button',
                    title: requestItem.status === 'participated' ? 'Teilnahmezeiten bearbeiten' : 'Als teilgenommen markieren',
                    'aria-label': requestItem.status === 'participated' ? 'Teilnahmezeiten bearbeiten' : 'Als teilgenommen markieren',
                },
            });
            participated.addEventListener('click', () => openParticipationDialog(requestItem));
            actionButtons.push(participated);
            if (requestItem.status !== 'not_participated') {
                const absent = element('button', {
                    className: 'participant-icon-button participant-icon-button-absent',
                    text: '⊘',
                    attributes: {type: 'button', title: 'Als nicht teilgenommen markieren', 'aria-label': 'Als nicht teilgenommen markieren'},
                });
                absent.addEventListener('click', async () => {
                    absent.disabled = true;
                    try {
                        await saveParticipation(requestItem, false);
                    } catch (error) {
                        toast(error.message, 'error');
                        absent.disabled = false;
                    }
                });
                actionButtons.push(absent);
            }
            return actionButtons;
        };
        // Nachricht, gewählte Aktivitäten und Hilfezeiträume je Anmeldung, aufklappbar.
        const buildParticipantDetails = (requestItem) => {
            const intervals = Array.isArray(requestItem.participationIntervals) ? requestItem.participationIntervals : [];
            const selectedActivities = Array.isArray(requestItem.selectedActivities) ? requestItem.selectedActivities : [];
            const hasMessage = typeof requestItem.message === 'string' && requestItem.message.trim() !== '';
            const detailsId = `event-helper-details-${requestItem.id}`;
            const detailsBody = (hasMessage || selectedActivities.length > 0 || intervals.length > 0)
                ? element('div', {className: 'event-helper-participant-details', attributes: {id: detailsId, hidden: 'hidden'}, children: [
                    ...(hasMessage ? [element('p', {className: 'event-helper-participant-message', text: requestItem.message})] : []),
                    ...((selectedActivities.length || intervals.length) ? [element('div', {className: 'event-helper-participant-meta', children: [
                        ...(selectedActivities.length ? [element('div', {className: 'selected-activity-list', children: [
                            element('strong', {text: 'Aktivitäten'}),
                            ...selectedActivities.map((activity) => element('span', {className: 'activity-chip', text: activity.name})),
                        ]})] : []),
                        ...(intervals.length ? [element('div', {className: 'participation-times', children: [
                            element('strong', {text: 'Zeiten'}),
                            element('ul', {className: 'participation-interval-summary', children: intervals.map((interval) => element('li', {text: `${interval.fromTime}–${interval.toTime} Uhr`}))}),
                        ]})] : []),
                    ]})] : []),
                ]})
                : null;
            return {detailsBody, detailsId};
        };
        // Macht `toggle` (Name- bzw. Zusammenfassungs-Button) zum Aufklapper für `detailsBody`,
        // falls vorhanden — sonst bleibt `toggle` ein reines Anzeige-Element ohne Verhalten.
        const wireDetailsToggle = (toggle, detailsBody, detailsId) => {
            if (!detailsBody) return toggle;
            const button = element('button', {
                className: 'event-helper-participant-toggle',
                attributes: {type: 'button', 'aria-expanded': 'false', 'aria-controls': detailsId},
                children: [toggle],
            });
            button.addEventListener('click', () => {
                const expanded = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', String(!expanded));
                detailsBody.hidden = expanded;
            });
            return button;
        };
        // Die Bubble übernimmt beides: zeigt die Mitgliedsnummer (falls verknüpft) und dient
        // zugleich als Verknüpfen-/Bearbeiten-Button — ein eigener zusätzlicher Button daneben
        // entfällt damit. `items` sind alle Anmeldungen, auf die eine Änderung der Verknüpfung
        // angewendet werden soll (bei zusammengeführten Mehrfachanmeldungen mehr als eine, siehe
        // `buildParticipantEntries`). Verknüpfen (bzw. eine bestehende — ggf. falsche — Verknüpfung
        // nachträglich bearbeiten) muss unabhängig vom Teilnahmestatus möglich sein, da die
        // Arbeitszeit-Erstattung zum Jahresende die Zuordnung zum Mitglied braucht.
        const buildMemberBubble = (items) => {
            const primary = items[0];
            const isLinked = !!primary.memberNumber;
            if (!canEditModule('event_helpers')) {
                // Ohne Bearbeitungsrecht nur eine reine Anzeige, keine leere Bubble ohne Verknüpfung.
                return isLinked ? element('span', {className: 'status-badge member-link-badge is-linked', text: primary.memberNumber}) : null;
            }
            const button = element('button', {
                className: `status-badge member-link-badge${isLinked ? ' is-linked' : ''}`,
                attributes: {
                    type: 'button',
                    title: isLinked ? 'Mitgliedsverknüpfung bearbeiten' : 'Mitglied verknüpfen',
                    'aria-label': isLinked ? 'Mitgliedsverknüpfung bearbeiten' : 'Mitglied verknüpfen',
                },
            });
            if (isLinked) {
                button.textContent = primary.memberNumber;
            } else {
                const icon = element('span', {className: 'member-link-badge-icon', attributes: {'aria-hidden': 'true'}});
                // Schlichtes Kettenglied-Icon statt Emoji — Vorbild: `buildMemberAccessNav`.
                icon.innerHTML = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>';
                button.append(icon, element('span', {text: 'Verknüpfen'}));
            }
            button.addEventListener('click', () => openLinkMemberDialog(items));

            return button;
        };
        const renderSingleParticipant = (requestItem) => {
            const actionButtons = buildParticipationActionButtons(requestItem);
            const {detailsBody, detailsId} = buildParticipantDetails(requestItem);
            const identity = element('div', {className: 'event-helper-participant-identity', children: [
                element('div', {className: 'event-helper-participant-name-row', children: [
                    element('strong', {text: `${requestItem.firstName} ${requestItem.lastName}`}),
                    ...(() => { const bubble = buildMemberBubble([requestItem]); return bubble ? [bubble] : []; })(),
                ]}),
                element('small', {text: `Angemeldet am ${new Date(requestItem.submittedAt).toLocaleString('de-DE')}`}),
            ]});
            return element('article', {className: `event-helper-participant status-${requestItem.status}`, children: [
                element('header', {className: 'event-helper-participant-header', children: [
                    wireDetailsToggle(identity, detailsBody, detailsId),
                    element('div', {className: 'event-helper-participant-side', children: [
                        element('span', {className: `participant-status status-${requestItem.status}`, text: participationStatus(requestItem)}),
                        ...(actionButtons.length ? [element('div', {className: 'participant-actions', children: actionButtons})] : []),
                    ]}),
                ]}),
                ...(detailsBody ? [detailsBody] : []),
            ]});
        };
        // Mehrere Anmeldungen derselben Person zur selben Veranstaltung (siehe
        // `buildParticipantEntries`) — eine gemeinsame Kopfzeile mit Name/Mitgliedsbubble, darunter
        // jede ursprüngliche Anmeldung als eigene Zeile mit ihren eigenen Teilnahme-Aktionen, damit
        // dabei nichts von der bisherigen Einzel-Erfassung verloren geht.
        const renderMergedParticipant = (items) => {
            const primary = items[0];
            const name = primary.memberFirstName && primary.memberLastName
                ? `${primary.memberFirstName} ${primary.memberLastName}`
                : `${primary.firstName} ${primary.lastName}`;
            const bubble = buildMemberBubble(items);
            const identity = element('div', {className: 'event-helper-participant-identity', children: [
                element('div', {className: 'event-helper-participant-name-row', children: [
                    element('strong', {text: name}),
                    ...(bubble ? [bubble] : []),
                ]}),
                element('small', {text: `${items.length} Anmeldungen zu dieser Veranstaltung zusammengeführt`}),
            ]});
            const rows = items.map((requestItem) => {
                const actionButtons = buildParticipationActionButtons(requestItem);
                const {detailsBody, detailsId} = buildParticipantDetails(requestItem);
                const summary = element('div', {className: 'event-helper-merged-row-summary', children: [
                    element('small', {text: `Angemeldet am ${new Date(requestItem.submittedAt).toLocaleString('de-DE')}`}),
                    element('span', {className: `participant-status status-${requestItem.status}`, text: participationStatus(requestItem)}),
                ]});
                return element('div', {className: `event-helper-merged-row status-${requestItem.status}`, children: [
                    element('div', {className: 'event-helper-merged-row-header', children: [
                        wireDetailsToggle(summary, detailsBody, detailsId),
                        ...(actionButtons.length ? [element('div', {className: 'participant-actions', children: actionButtons})] : []),
                    ]}),
                    ...(detailsBody ? [detailsBody] : []),
                ]});
            });
            return element('article', {className: 'event-helper-participant event-helper-participant-merged', children: [
                element('header', {className: 'event-helper-participant-header', children: [identity]}),
                element('div', {className: 'event-helper-merged-list', children: rows}),
            ]});
        };
        // Fasst mehrere Anmeldungen derselben Veranstaltung zu einer Karte zusammen, wenn sie mit
        // demselben Mitglied verknüpft sind (z. B. weil sich jemand aus Versehen zweimal angemeldet
        // hat) — die ursprüngliche Reihenfolge bleibt dabei anhand des ersten Vorkommens erhalten.
        const buildParticipantEntries = (requestList) => {
            const byMemberId = new Map();
            requestList.forEach((requestItem) => {
                if (!requestItem.memberId) return;
                if (!byMemberId.has(requestItem.memberId)) byMemberId.set(requestItem.memberId, []);
                byMemberId.get(requestItem.memberId).push(requestItem);
            });
            const rendered = new Set();
            const entries = [];
            requestList.forEach((requestItem) => {
                if (rendered.has(requestItem.id)) return;
                const group = requestItem.memberId ? byMemberId.get(requestItem.memberId) : [requestItem];
                group.forEach((groupItem) => rendered.add(groupItem.id));
                entries.push(group.length > 1 ? renderMergedParticipant(group) : renderSingleParticipant(requestItem));
            });
            return entries;
        };
        const renderEventHelperGroup = ({event, requests}) => {
            const participatedCount = requests.filter((requestItem) => requestItem.status === 'participated').length;
            return element('section', {className: 'event-helper-group', children: [
            element('header', {children: [
                element('div', {children: [
                    element('h3', {text: event.eventTitle}),
                    element('p', {text: `${new Date(`${event.eventDate}T00:00:00`).toLocaleDateString('de-DE')} · ${event.eventTime} Uhr`}),
                ]}),
                element('div', {className: 'event-helper-counts', children: [
                    element('span', {className: 'status-badge', text: `${participatedCount} ${participatedCount === 1 ? 'Helfer' : 'Helfer'}`}),
                    element('small', {text: `${requests.length} ${requests.length === 1 ? 'Person war' : 'Personen waren'} angemeldet`}),
                ]}),
            ]}),
            element('div', {className: 'event-helper-list', children: buildParticipantEntries(requests)}),
            ]});
        };
        const {
            currentYear, todayItems: todayGroups, upcomingItems: upcomingGroups,
            completedCurrentYearItems: completedCurrentYearGroups, archiveByYear: archiveGroups,
        } = bucketItemsByDate([...groups.values()], (group) => group.event.eventDate, (group) => group.event.eventTime);
        const eventSection = (title, eventGroups, modifier = '') => element('section', {
            className: `event-helper-section ${modifier}`.trim(),
            children: [
                element('div', {className: 'event-helper-section-heading', children: [
                    element('h3', {text: title}),
                    element('span', {className: 'status-badge', text: String(eventGroups.length)}),
                ]}),
                element('div', {className: 'event-helper-section-list', children: eventGroups.map(renderEventHelperGroup)}),
            ],
        });
        const eventArchive = (title, eventGroups) => element('details', {className: 'event-helper-archive', children: [
            element('summary', {children: [
                element('strong', {text: title}),
                element('span', {className: 'status-badge', text: String(eventGroups.length)}),
            ]}),
            element('div', {className: 'event-helper-archive-list', children: eventGroups.map(renderEventHelperGroup)}),
        ]});
        const sections = [
            ...(todayGroups.length ? [eventSection('Heute', todayGroups, 'event-helper-section-today')] : []),
            ...(upcomingGroups.length ? [eventSection('Kommende Veranstaltungen', upcomingGroups)] : []),
            ...(completedCurrentYearGroups.length
                ? [eventArchive(`Abgeschlossene Veranstaltungen ${currentYear}`, completedCurrentYearGroups)]
                : []),
            ...[...archiveGroups.entries()]
                .sort(([firstYear], [secondYear]) => secondYear - firstYear)
                .map(([year, eventGroups]) => eventArchive(`Archiv ${year}`, eventGroups)),
        ];
        workspace.replaceChildren(
            sectionHeading('Veranstaltungshelfer', 'Anmeldungen nach Veranstaltung gruppiert verwalten'),
            element('div', {className: 'event-helper-groups', children: sections.length ? sections : [emptyState('Noch keine Helferanmeldungen vorhanden.')]}),
        );
    };

    const showMembership = async () => {
        const data = await request('/api/admin/v1/membership-applications');

        const renderApplicationCard = (application) => {
            const primary = application.applicants[0];
            const people = application.applicants.map((person) => element('li', {children: [
                element('strong', {text: `${SALUTATION_LABELS[person.salutation] || person.salutation} ${person.firstName} ${person.lastName}`}),
                element('span', {text: ` · ${new Date(`${person.birthDate}T00:00:00`).toLocaleDateString('de-DE')} · ${person.street} ${person.houseNumber}, ${person.postalCode} ${person.city} · `}),
                ...(person.email ? [element('a', {text: `${person.email}`, attributes: {href: `mailto:${person.email} · `}})] : []),
                ...(person.phone ? [element('a', {text: `${person.phone}`, attributes: {href: `tel:${person.phone}`}})] : []),
            ]}));
            // Sobald ein Antrag als Mitglied angelegt oder abgelehnt wurde, sind beide Aktionen
            // (Freigabe/Ablehnung) hinfällig — für abgeschlossene Anträge bleibt `actions` dadurch
            // automatisch leer, ohne das hier gesondert abfragen zu müssen.
            const open = !application.releasedAt && !application.rejectedAt;
            const actions = [];
            if (canEditModule('membership_applications') && canEditModule('members') && open) {
                actions.push(actionButton('Als Mitglied anlegen', `/api/admin/v1/membership-applications/${application.id}/release`, showMembershipManagement, 'button', {
                    confirm: {
                        title: 'Mitglied anlegen',
                        description: 'Für jede Person dieses Antrags wird ein Mitglied in der Mitgliederverwaltung angelegt. Das kann nicht rückgängig gemacht werden.',
                        label: 'Mitglied anlegen',
                    },
                    success: 'Die Person(en) wurden als Mitglied angelegt.',
                }));
            }
            if (canEditModule('membership_applications') && open) {
                actions.push(actionButton('Ablehnen', `/api/admin/v1/membership-applications/${application.id}/reject`, showMembershipManagement, 'secondary-button', {
                    confirm: {
                        title: 'Mitgliedsantrag ablehnen',
                        description: 'Der Antrag wird als abgelehnt markiert und kann danach nicht mehr als Mitglied angelegt werden. Das kann nicht rückgängig gemacht werden.',
                        label: 'Ablehnen',
                    },
                    success: 'Der Mitgliedsantrag wurde abgelehnt.',
                }));
            }
            return element('article', {className: `management-card membership-admin-card${application.releasedAt ? ' is-released' : ''}${application.rejectedAt ? ' is-rejected' : ''}`, children: [
                element('header', {children: [
                    element('div', {children: [
                        element('strong', {text: primary ? `${primary.firstName} ${primary.lastName}` : application.id}),
                        element('small', {text: `${application.membershipType === 'family' ? 'Familie' : 'Einzelperson'} · ${application.applicants.length} ${application.applicants.length === 1 ? 'Person' : 'Personen'}`}),
                    ]}),
                    element('div', {className: 'membership-admin-card-badges', children: [
                        ...(open ? [element('span', {className: 'status-badge status-pending', text: 'Offen'})] : []),
                        ...(application.releasedAt ? [element('span', {className: 'status-badge status-released', text: 'Mitglied angelegt'})] : []),
                        ...(application.rejectedAt ? [element('span', {className: 'status-badge status-rejected', text: 'Abgelehnt'})] : []),
                    ]}),
                ]}),
                element('dl', {className: 'membership-meta', children: [
                    element('div', {children: [element('dt', {text: 'Eingang'}), element('dd', {text: new Date(application.submittedAt).toLocaleString('de-DE')})]}),
                    element('div', {children: [element('dt', {text: 'Kontoinhaber'}), element('dd', {text: application.accountHolder})]}),
                    element('div', {children: [element('dt', {text: 'IBAN'}), element('dd', {text: application.iban})]}),
                    element('div', {children: [element('dt', {text: 'Vorgang'}), element('dd', {text: application.id})]}),
                    ...(application.releasedAt ? [element('div', {children: [element('dt', {text: 'Mitglied angelegt'}), element('dd', {text: new Date(application.releasedAt).toLocaleString('de-DE')})]})] : []),
                    ...(application.rejectedAt ? [element('div', {children: [element('dt', {text: 'Abgelehnt am'}), element('dd', {text: new Date(application.rejectedAt).toLocaleString('de-DE')})]})] : []),
                ]}),
                element('details', {children: [
                    element('summary', {text: 'Personen und Kontaktdaten anzeigen'}),
                    element('ol', {className: 'membership-admin-people', children: people}),
                ]}),
                ...(application.rejectionReason ? [element('p', {className: 'failure-message', text: application.rejectionReason})] : []),
                ...(actions.length ? [element('div', {className: 'card-actions', children: actions})] : []),
            ]});
        };

        const activeApplications = data.items.filter((application) => !application.releasedAt && !application.rejectedAt);
        const completedApplications = data.items.filter((application) => application.releasedAt || application.rejectedAt);
        const completedByYear = new Map();
        completedApplications.forEach((application) => {
            const year = new Date(application.releasedAt || application.rejectedAt).getFullYear();
            if (!completedByYear.has(year)) completedByYear.set(year, []);
            completedByYear.get(year).push(application);
        });
        completedByYear.forEach((yearApplications) => yearApplications.sort(
            (left, right) => new Date(right.releasedAt || right.rejectedAt) - new Date(left.releasedAt || left.rejectedAt),
        ));
        const archive = (title, yearApplications) => element('details', {className: 'event-helper-archive', children: [
            element('summary', {children: [
                element('strong', {text: title}),
                element('span', {className: 'status-badge', text: String(yearApplications.length)}),
            ]}),
            element('div', {className: 'event-helper-archive-list', children: [
                element('div', {className: 'card-list', children: yearApplications.map(renderApplicationCard)}),
            ]}),
        ]});
        const archiveSections = [...completedByYear.entries()]
            .sort(([firstYear], [secondYear]) => secondYear - firstYear)
            .map(([year, yearApplications]) => archive(`Abgeschlossen ${year}`, yearApplications));

        workspace.replaceChildren(
            sectionHeading('Mitgliedsanträge', 'Eingegangene Anträge prüfen, als Mitglied anlegen oder ablehnen'),
            element('div', {className: 'management-toolbar', children: [element('span', {text: `${activeApplications.length} offene Anträge`})]}),
            element('div', {className: 'card-list', children: activeApplications.length ? activeApplications.map(renderApplicationCard) : [emptyState('Keine offenen Mitgliedsanträge vorhanden.')]}),
            ...(archiveSections.length ? [element('div', {className: 'event-helper-groups membership-archive-groups', children: archiveSections})] : []),
        );
    };

    const showMembershipDashboard = async () => {
        const stats = await request('/api/admin/v1/membership-dashboard');
        const tile = (label, value) => element('article', {className: 'stat-tile', children: [
            element('strong', {text: String(value)}),
            element('span', {text: label}),
        ]});
        workspace.replaceChildren(
            sectionHeading('Dashboard', 'Kennzahlen der Mitgliederverwaltung auf einen Blick'),
            element('div', {className: 'stat-tile-grid', children: [
                tile('Mitglieder gesamt', stats.totalMembers),
                tile('davon aktiv', stats.activeMembers),
                tile('Offene Mitgliedsanträge', stats.pendingApplications),
                tile('Austritte zum Jahresende', stats.leavingAtYearEnd),
                tile('Austritte aus Vorjahr', stats.leftLastYearEnd),
                tile('Beiträge gesamt pro Jahr', formatEuro(stats.totalContributionCents)),
            ]}),
            element('p', {className: 'empty-copy', text: 'Weitere Auswertungen folgen.'}),
        );
    };

    // Suchbares Textfeld über ein natives <datalist> statt eines langen <select> mit allen
    // Mitgliedern: Der Browser filtert die Vorschlagsliste selbst anhand der Eingabe (Name oder
    // Mitgliedsnummer), ein verstecktes Feld hält die tatsächlich gewählte Mitglieds-ID.
    const memberSearchField = (label, name, members, currentId, selectedId) => {
        const labelOf = (candidate) => `${candidate.firstName} ${candidate.lastName} (${candidate.memberNumber})`;
        const candidates = members.filter((candidate) => candidate.id !== currentId);
        const idByLabel = new Map(candidates.map((candidate) => [labelOf(candidate), candidate.id]));
        const selected = candidates.find((candidate) => candidate.id === selectedId);

        const listId = `${name}-options`;
        const hidden = element('input', {attributes: {type: 'hidden', name}});
        hidden.value = selectedId || '';
        const search = element('input', {attributes: {
            type: 'text', id: name, list: listId, autocomplete: 'off',
            placeholder: 'Name oder Mitgliedsnummer suchen …',
        }});
        search.value = selected ? labelOf(selected) : '';
        const datalist = element('datalist', {attributes: {id: listId}, children: candidates.map(
            (candidate) => element('option', {attributes: {value: labelOf(candidate)}}),
        )});
        const sync = () => { hidden.value = idByLabel.get(search.value.trim()) || ''; };
        search.addEventListener('input', sync);
        search.addEventListener('change', sync);

        return element('label', {className: 'field', children: [element('span', {text: label}), search, datalist, hidden]});
    };

    const memberOpenButton = (entry, currentId, openOther) => entry.id === currentId
        ? null
        : (() => {
            const button = element('button', {className: 'secondary-button', text: 'Öffnen →', attributes: {type: 'button'}});
            button.addEventListener('click', () => openOther(entry.id));

            return button;
        })();

    const memberListItem = (entry, currentId, openOther) => {
        const openButton = memberOpenButton(entry, currentId, openOther);

        return element('li', {children: [
            element('div', {className: `member-list-card${entry.id === currentId ? ' is-current' : ''}`, children: [
                element('strong', {text: `${entry.firstName} ${entry.lastName}`}),
                element('span', {text: ` · ${FAMILY_ROLE_LABELS[entry.familyRole] || entry.familyRole} · ${entry.memberNumber}`}),
            ]}),
            ...(openButton ? [openButton] : []),
        ]});
    };

    // Ist das Austrittsdatum erreicht (heute oder in der Vergangenheit), gilt das Mitglied als
    // ausgetreten — unabhängig von „Beitragspflichtig“, das dann seine Bedeutung verliert.
    const hasMemberLeft = (leftAt) => !!leftAt && leftAt <= new Date().toISOString().slice(0, 10);

    // Grund, warum für ein Mitglied kein Beitrag anfällt (falls zutreffend). Ausgetreten hat
    // Vorrang vor Vorstand, da die Vorstandsfunktion nach dem Austritt keine Rolle mehr spielt.
    // „Vorstand“ als Hinweis erscheint nur, wenn die Funktion tatsächlich Vorstand ist — wurde bei
    // einem „Mitglied“ nur das Häkchen „Beitragspflichtig“ entfernt, steht stattdessen der
    // allgemeinere Hinweis „Nicht beitragspflichtig“.
    const contributionExemptionReason = (entry) => {
        if (hasMemberLeft(entry.leftAt)) return 'Ausgetreten';
        if (entry.contributionLiable === false) return entry.function === 'board' ? 'Vorstand' : 'Nicht beitragspflichtig';

        return null;
    };

    // Die einzelnen Beitragssatz-Positionen, aus denen sich der Beitrag eines Mitglieds
    // zusammensetzt (laufender Beitrag nach Kategorie + ggf. Arbeitseinsatz-Zuschlag).
    // Ausgetretene bzw. Vorstandsmitglieder sind beitragsfrei und werden unabhängig von (ggf.
    // noch nicht neu berechneten) gespeicherten Werten sofort mit 0 € und entsprechendem Hinweis
    // ausgewiesen.
    const contributionPositions = (entry) => {
        const exemptionReason = contributionExemptionReason(entry);

        return exemptionReason
            ? [[exemptionReason, 0]]
            : [
                ...(entry.contributionCategory
                    ? [[CONTRIBUTION_CATEGORY_LABELS[entry.contributionCategory] || entry.contributionCategory, entry.contributionAmountCents]]
                    : []),
                ...(entry.workAssignmentSurchargeCents != null ? [['Arbeitseinsatz-Zuschlag', entry.workAssignmentSurchargeCents]] : []),
            ];
    };

    const payerListItem = (entry, currentId, openOther) => {
        const openButton = memberOpenButton(entry, currentId, openOther);
        const positions = contributionPositions(entry);
        const sumCents = positions.reduce((sum, [, cents]) => sum + (cents || 0), 0);

        return element('li', {className: entry.id === currentId ? 'is-current' : '', children: [
            element('div', {className: 'member-payer-row', children: [
                element('div', {className: 'member-payer-info', children: [
                    element('div', {className: 'member-payer-entry-header', children: [
                        element('strong', {text: `${entry.firstName} ${entry.lastName}`}),
                        element('span', {text: ` · ${FAMILY_ROLE_LABELS[entry.familyRole] || entry.familyRole} · ${entry.memberNumber}`}),
                    ]}),
                    element('ul', {className: 'member-payer-positions', children: positions.length
                        ? positions.map(([label, cents]) => element('li', {children: [
                            element('span', {text: label}),
                            element('span', {text: ` · ${formatEuro(cents)}`}),
                        ]}))
                        : [element('li', {className: 'empty-copy', text: 'Noch kein Beitrag berechnet.'})]}),
                ]}),
                element('strong', {className: 'member-payer-sum', text: formatEuro(sumCents)}),
            ]}),
            ...(openButton ? [openButton] : []),
        ]});
    };

    const openMemberDialog = async (member, onSaved) => {
        // Immer die vollständige, ungefilterte Mitgliederliste laden (unabhängig von einer evtl.
        // aktiven Suche in der Tabelle), damit die Zahler-Suche jedes Mitglied findet.
        let [household, payerCandidates] = await Promise.all([
            member ? request(`/api/admin/v1/members/${member.id}/household`) : Promise.resolve(null),
            request('/api/admin/v1/members').then((data) => data.items),
        ]);
        const dialog = element('dialog', {className: 'activity-dialog member-dialog'});
        const suffix = member?.id || 'new';
        const openOther = async (id) => {
            dialog.close();
            const other = await request(`/api/admin/v1/members/${id}`);
            await openMemberDialog(other, onSaved);
        };

        const salutation = selectField('Anrede', `member-salutation-${suffix}`, Object.entries(SALUTATION_LABELS), member?.salutation || 'mr');
        const lastName = field('Name', `member-last-name-${suffix}`, member?.lastName || '');
        const firstName = field('Vorname', `member-first-name-${suffix}`, member?.firstName || '');
        const birthDate = field('Geburtsdatum', `member-birth-date-${suffix}`, member?.birthDate || '', 'date');
        const birthDateLabel = birthDate.querySelector('span');
        const birthDateInput = birthDate.querySelector('input');
        const updateBirthDateLabel = () => {
            const born = birthDateInput.value ? new Date(`${birthDateInput.value}T00:00:00`) : null;
            if (!born || Number.isNaN(born.getTime())) {
                birthDateLabel.textContent = 'Geburtsdatum';
                return;
            }
            const today = new Date();
            let age = today.getFullYear() - born.getFullYear();
            const hadBirthdayThisYear = today.getMonth() > born.getMonth()
                || (today.getMonth() === born.getMonth() && today.getDate() >= born.getDate());
            if (!hadBirthdayThisYear) age -= 1;
            birthDateLabel.textContent = `Geburtsdatum (${age} ${age === 1 ? 'Jahr' : 'Jahre'})`;
        };
        birthDateInput.addEventListener('input', updateBirthDateLabel);
        updateBirthDateLabel();
        const street = field('Straße', `member-street-${suffix}`, member?.street || '');
        const postalCode = field('PLZ', `member-postal-code-${suffix}`, member?.postalCode || '');
        const city = field('Ort', `member-city-${suffix}`, member?.city || '');
        const email = field('E-Mail', `member-email-${suffix}`, member?.email || '', 'email');
        const phone = field('Telefon', `member-phone-${suffix}`, member?.phone || '');

        const memberNumberField = member
            ? (() => {
                const readonlyField = field('Mitgliedsnummer', `member-number-${suffix}`, member.memberNumber);
                readonlyField.querySelector('input').readOnly = true;

                return readonlyField;
            })()
            : element('p', {className: 'empty-copy', text: 'Die Mitgliedsnummer wird automatisch vergeben.'});
        const primaryMemberNumber = field('Hauptnummer', `member-primary-number-${suffix}`, member?.primaryMemberNumber || '');
        const familyRole = selectField('Familienzugehörigkeit', `member-family-role-${suffix}`, Object.entries(FAMILY_ROLE_LABELS), member?.familyRole || 'none');

        const joinedAt = field('Eintrittsdatum', `member-joined-at-${suffix}`, member?.joinedAt || new Date().toISOString().slice(0, 10), 'date');
        const leftAt = field('Austrittsdatum (optional)', `member-left-at-${suffix}`, member?.leftAt || '', 'date');
        const active = element('input', {attributes: {type: 'checkbox'}});
        active.checked = member ? member.active : true;
        const memberFunction = selectField('Funktion', `member-function-${suffix}`, Object.entries(MEMBER_FUNCTION_LABELS), member?.function || 'member');

        const accountHolder = field('Kontoinhaber', `member-account-holder-${suffix}`, member?.accountHolder || '');
        const iban = field('IBAN', `member-iban-${suffix}`, member?.iban || '');
        const bankName = field('Bank (optional)', `member-bank-name-${suffix}`, member?.bankName || '');
        const mandateReference = field('Mandatsreferenz', `member-mandate-reference-${suffix}`, member?.mandateReference || '');
        const mandateValidFrom = field('Mandat gültig von', `member-mandate-valid-from-${suffix}`, member?.mandateValidFrom || '', 'date');
        const mandateValidUntil = field('Mandat gültig bis', `member-mandate-valid-until-${suffix}`, member?.mandateValidUntil || '', 'date');

        const paymentMethod = selectField('Zahlart', `member-payment-method-${suffix}`, Object.entries(PAYMENT_METHOD_LABELS), member?.paymentMethod || 'sepa_direct_debit');
        const paymentMethodSelect = paymentMethod.querySelector('select');
        const paymentInterval = selectField('Zahlintervall', `member-payment-interval-${suffix}`, Object.entries(PAYMENT_INTERVAL_LABELS).filter(([value]) => value !== 'once'), member?.paymentInterval || 'yearly');
        const paymentDay = radioGroup(`member-payment-day-${suffix}`, 'Zahlung am', Object.entries(PAYMENT_DAY_LABELS), member?.paymentDay || 'first');
        const payerType = selectField('Zahler', `member-payer-type-${suffix}`, Object.entries(PAYER_TYPE_LABELS), member?.payerType || 'self_payer');
        const payerMember = memberSearchField('Zahlendes Mitglied', `member-payer-id-${suffix}`, payerCandidates, member?.id, member?.payerMemberId || '');
        const payerMemberSelect = payerMember.querySelector('input[type="hidden"]');
        const togglePayerMember = () => { payerMember.hidden = payerTypeSelect.value !== 'other_member'; };
        const payerTypeSelect = payerType.querySelector('select');
        payerTypeSelect.addEventListener('change', togglePayerMember);
        togglePayerMember();

        const nextBookingMonth = selectField('Nächste Buchung (Monat)', `member-next-booking-month-${suffix}`, Array.from({length: 12}, (_, index) => [String(index + 1), String(index + 1).padStart(2, '0')]), String(member?.nextBookingMonth || 3));
        const nextBookingYear = field('Nächste Buchung (Jahr)', `member-next-booking-year-${suffix}`, String(member?.nextBookingYear || (new Date().getFullYear() + 1)), 'number');

        // Vorstandsmitglieder sind laut Satzung beitragsfrei: Ist das Mitglied nicht
        // beitragspflichtig, werden Konto-, Bank- und Zahlungsdaten gesperrt (bleiben aber
        // gespeichert) und der Beitrag wird bei der nächsten Berechnung mit 0 € geführt. Ist das
        // Austrittsdatum bereits erreicht, ist „Beitragspflichtig“ ohnehin bedeutungslos — die
        // Checkbox wird dann gesperrt (Wert bleibt erhalten) und dieselben Felder gesperrt.
        const contributionLiable = element('input', {attributes: {type: 'checkbox'}});
        contributionLiable.checked = member ? member.contributionLiable !== false : true;
        // Zahlungsdaten hängen nur an „Beitragspflichtig“; Kontodaten zusätzlich daran, dass
        // überhaupt SEPA-Lastschrift als Zahlart gewählt ist — nur dafür existiert ein Mandat.
        const paymentRestrictedFields = [paymentMethod, paymentInterval, payerType, nextBookingMonth, nextBookingYear];
        const accountRestrictedFields = [accountHolder, iban, bankName, mandateReference, mandateValidFrom, mandateValidUntil];
        const applyContributionLiableState = () => {
            const left = hasMemberLeft(leftAt.querySelector('input').value);
            contributionLiable.disabled = left;
            const liable = contributionLiable.checked && !left;
            paymentRestrictedFields.forEach((wrapper) => {
                const control = wrapper.querySelector('input, select');
                if (control) control.disabled = !liable;
            });
            accountRestrictedFields.forEach((wrapper) => {
                const control = wrapper.querySelector('input, select');
                if (control) control.disabled = !liable || paymentMethodSelect.value !== 'sepa_direct_debit';
            });
            paymentDay.querySelectorAll('input').forEach((input) => { input.disabled = !liable; });
            const payerSearchInput = payerMember.querySelector('input[type="text"]');
            if (payerSearchInput) payerSearchInput.disabled = !liable;
        };
        contributionLiable.addEventListener('change', applyContributionLiableState);
        leftAt.querySelector('input').addEventListener('input', applyContributionLiableState);
        paymentMethodSelect.addEventListener('change', applyContributionLiableState);
        applyContributionLiableState();

        // Vorstandsmitglieder sind laut Satzung beitragsfrei: Beim Umstellen der Funktion auf
        // „Vorstand“ wird „Beitragspflichtig“ direkt im Formular deaktiviert, beim Umstellen weg
        // von „Vorstand“ wieder aktiviert — maßgeblich (auch ohne JavaScript bzw. bei einem
        // direkten API-Aufruf) ist aber der serverseitige Automatismus in `UpdateMemberUseCase`,
        // der beim Speichern denselben Wechsel erkennt, den Haken entsprechend setzt und danach den
        // Beitrag für das Mitglied und seinen ganzen Haushalt automatisch neu berechnet.
        const memberFunctionSelect = memberFunction.querySelector('select');
        memberFunctionSelect.addEventListener('change', () => {
            contributionLiable.checked = memberFunctionSelect.value !== 'board';
            applyContributionLiableState();
        });

        const message = formMessage();
        const submit = element('button', {className: 'button', text: member ? 'Änderungen speichern' : 'Mitglied anlegen', attributes: {type: 'submit'}});
        const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
        const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
        const deleteButton = member && canEditModule('members')
            ? element('button', {className: 'secondary-button danger-button', text: 'Mitglied löschen', attributes: {type: 'button'}})
            : null;
        if (deleteButton) {
            deleteButton.addEventListener('click', async () => {
                const confirmed = await confirmAction(
                    'Mitglied löschen',
                    `„${member.firstName} ${member.lastName}“ (${member.memberNumber}) wird endgültig gelöscht, inklusive Bemerkungen und Beitragshistorie. Das kann nicht rückgängig gemacht werden.`,
                    'Löschen',
                );
                if (!confirmed) return;
                deleteButton.disabled = true;
                try {
                    if (!(await deleteMemberWithOptionalPin(member.id))) {
                        deleteButton.disabled = false;
                        return;
                    }
                    toast('Das Mitglied wurde gelöscht.');
                    dialog.close();
                    await onSaved();
                } catch (error) {
                    toast(error.message, 'error');
                    deleteButton.disabled = false;
                }
            });
        }

        // In eine eigene Funktion ausgelagert, damit „Beitrag neu berechnen“ nur diesen Ausschnitt
        // austauschen kann, statt den ganzen Dialog zu schließen (siehe Klick-Handler unten).
        const contributionInfoContent = (currentMember) => {
            const exemptionReason = contributionExemptionReason(currentMember);

            return [
                element('p', {children: [
                    element('strong', {text: exemptionReason
                        ?? (currentMember.contributionCategory ? CONTRIBUTION_CATEGORY_LABELS[currentMember.contributionCategory] || currentMember.contributionCategory : 'Noch nicht berechnet')}),
                    element('span', {text: ` · ${formatEuro(exemptionReason ? 0 : currentMember.contributionAmountCents)} pro Jahr`}),
                ]}),
                ...(!exemptionReason && currentMember.workAssignmentSurchargeCents != null ? [element('p', {children: [
                    element('strong', {text: 'Arbeitseinsatz-Zuschlag'}),
                    element('span', {text: ` · zzgl. ${formatEuro(currentMember.workAssignmentSurchargeCents)} pro Jahr (bei Ableistung der Gemeinschaftsstunden erstattungsfähig)`}),
                ]})] : []),
                element('p', {className: 'field-hint', text: 'Erhaltene Beitragssätze (einmalige Gebühren):'}),
                element('ul', {className: 'member-remarks', children: currentMember.oneTimeCharges.length
                    ? currentMember.oneTimeCharges.map((charge) => element('li', {children: [
                        element('strong', {text: charge.label}),
                        element('span', {text: ` · ${formatEuro(charge.amountCents)} · ${new Date(charge.chargedAt).toLocaleDateString('de-DE')}`}),
                    ]}))
                    : [element('li', {className: 'empty-copy', text: 'Keine einmaligen Gebühren berechnet.'})]}),
            ];
        };
        const contributionInfo = member ? element('div', {children: contributionInfoContent(member)}) : null;
        const contributionSection = member ? element('fieldset', {children: [
            element('legend', {text: 'Beitrag'}),
            contributionInfo,
        ]}) : null;
        const recalculate = member ? element('button', {className: 'secondary-button', text: 'Beitrag neu berechnen', attributes: {type: 'button'}}) : null;
        if (recalculate) {
            recalculate.addEventListener('click', async () => {
                // Erst die aktuell im Formular eingetragenen Werte speichern (z. B. eine gerade erst
                // umgestellte Funktion), sonst würde die Berechnung mit dem zuletzt gespeicherten
                // Stand aus der Datenbank rechnen statt mit den noch ungespeicherten Änderungen.
                // `reportValidity()` entspricht derselben Pflichtfeld-Prüfung wie beim „Speichern“.
                if (!form.reportValidity()) return;
                recalculate.disabled = true;
                try {
                    member = await persistMember();
                    member = await request(`/api/admin/v1/members/${member.id}/recalculate-contribution`, {method: 'POST'});
                    // Bewusst kein dialog.close(): nur die Beitragsdaten des gerade geöffneten
                    // Mitglieds werden hier neu geladen und ausgetauscht — der Dialog bleibt offen.
                    household = await request(`/api/admin/v1/members/${member.id}/household`);
                    contributionInfo.replaceChildren(...contributionInfoContent(member));
                    if (payerBody) payerBody.replaceChildren(...payerContent(household, member));
                    // Speichern bzw. die Neuberechnung können „Beitragspflichtig“ serverseitig
                    // verändert haben (z. B. wenn jetzt ein Vorstandsmitglied im Haushalt ist) —
                    // Haken und davon abhängige gesperrte Felder im offenen Formular nachziehen.
                    contributionLiable.checked = member.contributionLiable !== false;
                    applyContributionLiableState();
                    toast('Die Änderungen wurden gespeichert und der Beitrag für die ganze Familie neu berechnet.');
                    await onSaved();
                } catch (error) {
                    message.textContent = error.message;
                    toast(error.message, 'error');
                } finally {
                    recalculate.disabled = false;
                }
            });
            contributionSection.append(
                recalculate,
                element('small', {text: 'Speichert zuerst alle Änderungen auf diesem Formular und berechnet danach den Beitrag für die gesamte Familie (alle Mitglieder mit derselben Hauptnummer) neu.'}),
            );
        }

        const remarksList = member ? element('ul', {className: 'member-remarks', children: member.remarks.length
            ? member.remarks.map((remark) => element('li', {children: [
                element('strong', {text: new Date(remark.createdAt).toLocaleString('de-DE')}),
                remark.authorDisplayName ? element('span', {text: ` · ${remark.authorDisplayName}`}) : null,
                element('p', {text: remark.text}),
            ].filter(Boolean)}))
            : [element('li', {className: 'empty-copy', text: 'Noch keine Bemerkungen.'})]}) : null;
        const newRemark = member ? field('Neue Bemerkung', `member-remark-${suffix}`, '', 'textarea') : null;
        const addRemarkButton = member ? element('button', {className: 'secondary-button', text: 'Bemerkung hinzufügen', attributes: {type: 'button'}}) : null;
        if (addRemarkButton) {
            addRemarkButton.addEventListener('click', async () => {
                const text = newRemark.querySelector('textarea').value.trim();
                if (!text) return;
                addRemarkButton.disabled = true;
                try {
                    await request(`/api/admin/v1/members/${member.id}/remarks`, {method: 'POST', body: JSON.stringify({text})});
                    toast('Die Bemerkung wurde hinzugefügt.');
                    dialog.close();
                    await onSaved();
                } catch (error) {
                    toast(error.message, 'error');
                    addRemarkButton.disabled = false;
                }
            });
        }

        const householdSection = member ? element('fieldset', {children: [
            element('legend', {text: 'Familienzugehörigkeit'}),
            element('ul', {className: 'member-household-list', children: household.householdMembers.length
                ? household.householdMembers.map((entry) => memberListItem(entry, member.id, openOther))
                : [element('li', {className: 'empty-copy', text: 'Keine weiteren Familienmitglieder.'})]}),
        ]}) : null;

        // Ebenfalls ausgelagert, damit „Beitrag neu berechnen“ diesen Ausschnitt austauschen kann,
        // ohne den Dialog zu schließen (siehe Klick-Handler oben bei `recalculate`).
        const payerContent = (currentHousehold, currentMember) => [
            element('p', {className: 'field-hint', text: currentHousehold.payer.id === currentMember.id
                ? 'Dieses Mitglied zahlt selbst, zusammen für:'
                : `Der Beitrag wird gezahlt von ${currentHousehold.payer.firstName} ${currentHousehold.payer.lastName} (${currentHousehold.payer.memberNumber}), zusammen für:`}),
            element('ul', {className: 'member-payer-list', children: currentHousehold.payerEntries.map((entry) => payerListItem(entry, currentMember.id, openOther))}),
            element('div', {className: 'member-payer-total', children: [
                element('span', {text: 'Gesamtbeitrag pro Jahr'}),
                // Aus denselben Positionen wie die Liste darüber berechnet (statt aus dem vom
                // Server gelieferten Rohwert), damit ein gerade erst gesetztes „Beitragspflichtig“-
                // Häkchen die Summe sofort auf 0 € zieht, statt erst nach „Beitrag neu berechnen“.
                element('span', {text: formatEuro(currentHousehold.payerEntries.reduce(
                    (sum, entry) => sum + contributionPositions(entry).reduce((entrySum, [, cents]) => entrySum + (cents || 0), 0),
                    0,
                ))}),
            ]}),
        ];
        const payerBody = member ? element('div', {children: payerContent(household, member)}) : null;
        const payerSection = member ? element('fieldset', {children: [
            element('legend', {text: 'Gesamtberechnung für den Zahler'}),
            payerBody,
        ]}) : null;

        const tabs = [
            ['stammdaten', 'Stammdaten', [
                element('fieldset', {children: [
                    element('legend', {text: 'Persönliche Daten'}),
                    fieldRow([memberNumberField, primaryMemberNumber]),
                    fieldRow([salutation]),
                    fieldRow([firstName, lastName]),
                    fieldRow([birthDate, familyRole]),
                    element('small', {text: 'Familienangehörige erhalten hier die Mitgliedsnummer des Hauptmitglieds.'}),
                ]}),
                ...(householdSection ? [householdSection] : []),
            ]],
            ['kontakt', 'Kontaktdaten', [
                element('fieldset', {children: [element('legend', {text: 'Adresse'}), street, fieldRow([postalCode, city])]}),
                element('fieldset', {children: [element('legend', {text: 'Kontakt'}), email, phone]}),
            ]],
            ['verein', 'Vereinsdaten', [
                element('fieldset', {children: [
                    element('legend', {text: 'Vereinsdaten'}),
                    memberFunction,
                    element('label', {className: 'check-field', children: [active, element('span', {text: 'Aktives Mitglied'})]}),
                    fieldRow([joinedAt, leftAt]),
                ]}),
            ]],
            ['beitrag', 'Beitragsdaten', [
                element('fieldset', {children: [
                    element('legend', {text: 'Beitragspflicht'}),
                    element('label', {className: 'check-field', children: [contributionLiable, element('span', {text: 'Beitragspflichtig'})]}),
                    element('small', {text: 'Vorstandsmitglieder sind laut Satzung beitragsfrei — und mit ihnen ihre ganze Familie: Sobald „Beitrag neu berechnen“ läuft (hier direkt oder über „Beiträge für alle Mitglieder neu berechnen“), wird dieser Haken bei allen Mitgliedern der Familie automatisch entfernt, sobald mindestens eines von ihnen Vorstand ist. Beim Umstellen der Funktion auf/von „Vorstand“ (Reiter „Vereinsdaten“) passiert dasselbe zusätzlich direkt beim Speichern. Ist der Haken entfernt, werden Konto-, Bank- und Zahlungsdaten gesperrt und der Beitrag mit 0 € und dem Hinweis „Vorstand“ geführt.'}),
                ]}),
                element('fieldset', {children: [
                    element('legend', {text: 'Zahlungsdaten'}),
                    fieldRow([paymentMethod, paymentInterval]),
                    paymentDay,
                    fieldRow([payerType, payerMember]),
                    nextBookingMonth, nextBookingYear,
                ]}),
                element('fieldset', {children: [
                    element('legend', {text: 'Kontodaten'}),
                    accountHolder, iban, bankName,
                    fieldRow([mandateReference, mandateValidFrom, mandateValidUntil], 3),
                    element('small', {text: 'Kontoinhaber, IBAN und Mandatsreferenz sind nur für Selbstzahler mit SEPA-Lastschrift erforderlich und werden nur bei dieser Zahlart bearbeitbar. „Gültig von/bis“ stammt aus dem Sage-GS-Bestand (MANDATABDATUM/MANDATBISDATUM) und kann hier gepflegt werden.'}),
                ]}),
                ...(contributionSection ? [contributionSection] : []),
                ...(payerSection ? [payerSection] : []),
            ]],
            ...(member ? [['bemerkungen', 'Bemerkungen', [
                element('fieldset', {children: [element('legend', {text: 'Bemerkungen'}), remarksList, newRemark, addRemarkButton]}),
            ]]] : []),
        ];

        let activeTab = tabs[0][0];
        const tabPanels = tabs.map(([key, , children]) => [key, element('div', {className: 'member-dialog-panel', children})]);
        const tabStrip = element('nav', {className: 'member-dialog-tab-strip', attributes: {'aria-label': 'Mitgliedsdaten'}});
        const updatePanels = () => {
            tabPanels.forEach(([key, panel]) => { panel.hidden = key !== activeTab; });
        };
        const updateTabStrip = () => {
            tabStrip.replaceChildren(...tabs.map(([key, label]) => {
                const button = element('button', {className: `sub-tab${key === activeTab ? ' active' : ''}`, text: label, attributes: {type: 'button'}});
                button.addEventListener('click', () => { activeTab = key; updateTabStrip(); updatePanels(); });

                return button;
            }));
        };
        updateTabStrip();
        updatePanels();

        const form = element('form', {className: 'activity-dialog-content member-dialog-content', children: [
            element('header', {children: [
                element('div', {children: [
                    element('p', {className: 'eyebrow', text: member ? 'Mitglied bearbeiten' : 'Neues Mitglied'}),
                    element('h2', {text: member ? `${member.firstName} ${member.lastName}` : 'Mitglied anlegen'}),
                ]}),
            ]}),
            tabStrip,
            ...tabPanels.map(([, panel]) => panel),
            message,
            element('div', {className: 'confirm-dialog-actions', children: [...(deleteButton ? [deleteButton] : []), cancel, submit]}),
        ]});
        [lastName, firstName, street, postalCode, city].forEach((wrapper) => {
            wrapper.querySelector('input').required = true;
        });
        cancel.addEventListener('click', () => dialog.close());
        close.addEventListener('click', () => dialog.close());
        // In eine eigene Funktion ausgelagert, damit „Beitrag neu berechnen“ (siehe oben bei
        // `recalculate`) dieselben, gerade im Formular eingetragenen Werte speichern kann, statt
        // die zuletzt gespeicherten Daten aus der Datenbank neu zu berechnen — sonst müsste man vor
        // „Beitrag neu berechnen“ immer erst manuell „Änderungen speichern“ klicken, z. B. nach
        // einer Umstellung der Funktion auf „Vorstand“.
        const buildPayload = () => ({
            primaryMemberNumber: primaryMemberNumber.querySelector('input').value.trim() || null,
            salutation: salutation.querySelector('select').value,
            lastName: lastName.querySelector('input').value,
            firstName: firstName.querySelector('input').value,
            birthDate: birthDate.querySelector('input').value,
            street: street.querySelector('input').value,
            postalCode: postalCode.querySelector('input').value,
            city: city.querySelector('input').value,
            email: email.querySelector('input').value || null,
            phone: phone.querySelector('input').value || null,
            familyRole: familyRole.querySelector('select').value,
            joinedAt: joinedAt.querySelector('input').value,
            leftAt: leftAt.querySelector('input').value || null,
            active: active.checked,
            function: memberFunction.querySelector('select').value,
            accountHolder: accountHolder.querySelector('input').value || null,
            iban: iban.querySelector('input').value || null,
            bankName: bankName.querySelector('input').value || null,
            mandateReference: mandateReference.querySelector('input').value || null,
            mandateValidFrom: mandateValidFrom.querySelector('input').value || null,
            mandateValidUntil: mandateValidUntil.querySelector('input').value || null,
            paymentMethod: paymentMethod.querySelector('select').value,
            paymentInterval: paymentInterval.querySelector('select').value,
            paymentDay: dialog.querySelector(`input[name="member-payment-day-${suffix}"]:checked`)?.value || 'first',
            payerType: payerTypeSelect.value,
            payerMemberId: payerTypeSelect.value === 'other_member' ? (payerMemberSelect.value || null) : null,
            nextBookingMonth: Number.parseInt(nextBookingMonth.querySelector('select').value, 10),
            nextBookingYear: Number.parseInt(nextBookingYear.querySelector('input').value, 10),
            contributionLiable: contributionLiable.checked,
        });
        const persistMember = () => member
            ? request(`/api/admin/v1/members/${member.id}`, {
                method: 'PUT',
                body: JSON.stringify({...buildPayload(), memberNumber: member.memberNumber, version: member.version}),
            })
            : request('/api/admin/v1/members', {method: 'POST', body: JSON.stringify(buildPayload())});
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            submit.disabled = true;
            try {
                const wasNew = !member;
                member = await persistMember();
                toast(wasNew ? 'Das Mitglied wurde angelegt.' : 'Das Mitglied wurde gespeichert.');
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
    };

    const openMemberImportDialog = (onImported) => {
        const dialog = element('dialog', {className: 'activity-dialog'});
        const fileInput = element('input', {attributes: {type: 'file', accept: '.csv,.json,.xml'}});
        const fileField = element('label', {className: 'field', children: [element('span', {text: 'Datei'}), fileInput]});
        const format = selectField('Dateiformat', 'member-import-format', [['csv', 'CSV'], ['json', 'JSON'], ['xml', 'XML']], 'csv');
        const message = formMessage();
        const submit = element('button', {className: 'button', text: 'Importieren', attributes: {type: 'submit'}});
        const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
        const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
        const form = element('form', {className: 'activity-dialog-content', children: [
            element('header', {children: [
                element('div', {children: [
                    element('p', {className: 'eyebrow', text: 'Mitglieder'}),
                    element('h2', {text: 'Mitglieder importieren'}),
                ]}),
            ]}),
            fileField, format,
            message,
            element('div', {className: 'confirm-dialog-actions', children: [cancel, submit]}),
        ]});
        cancel.addEventListener('click', () => dialog.close());
        close.addEventListener('click', () => dialog.close());
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const file = fileInput.files[0];
            if (!file) {
                message.textContent = 'Bitte zuerst eine Datei auswählen.';
                return;
            }
            const formData = new FormData();
            formData.append('file', file);
            formData.append('format', format.querySelector('select').value);
            submit.disabled = true;
            try {
                const result = await request('/api/admin/v1/members/import', {method: 'POST', body: formData});
                const errorSuffix = result.errors.length ? `, ${result.errors.length} Zeile(n) mit Fehlern` : '';
                toast(`${result.created} Mitglied(er) angelegt, ${result.updated} aktualisiert${errorSuffix}.`, result.errors.length ? 'info' : 'success');
                dialog.close();
                await onImported();
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
    };

    const openMemberExportDialog = () => {
        const dialog = element('dialog', {className: 'activity-dialog'});
        const format = selectField('Dateiformat', 'member-export-format', [['csv', 'CSV'], ['json', 'JSON'], ['xml', 'XML']], 'csv');
        const message = formMessage();
        const submit = element('button', {className: 'button', text: 'Exportieren', attributes: {type: 'submit'}});
        const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
        const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
        const form = element('form', {className: 'activity-dialog-content', children: [
            element('header', {children: [
                element('div', {children: [
                    element('p', {className: 'eyebrow', text: 'Mitglieder'}),
                    element('h2', {text: 'Mitglieder exportieren'}),
                ]}),
            ]}),
            format,
            message,
            element('div', {className: 'confirm-dialog-actions', children: [cancel, submit]}),
        ]});
        cancel.addEventListener('click', () => dialog.close());
        close.addEventListener('click', () => dialog.close());
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            submit.disabled = true;
            const selectedFormat = format.querySelector('select').value;
            try {
                const response = await fetch(`/api/admin/v1/members/export?format=${selectedFormat}`, {credentials: 'same-origin'});
                if (!response.ok) throw new Error('Der Export ist fehlgeschlagen.');
                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const link = element('a', {attributes: {href: url, download: `mitglieder.${selectedFormat}`}});
                document.body.append(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
                dialog.close();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
            } finally {
                submit.disabled = false;
            }
        });
        dialog.addEventListener('close', () => dialog.remove());
        dialog.append(close, form);
        document.body.append(dialog);
        dialog.showModal();
    };

    // Zeigt, welche Mitglieder eine Sammel-Neuberechnung übersprungen hat und warum — damit z. B.
    // eine fehlende/ungültige IBAN nicht als anonyme Fehlermeldung ohne erkennbaren Bezug zu einem
    // Mitglied endet (`RecalculateAllMemberContributionsUseCase` liefert dafür je übersprungenem
    // Datensatz die Mitgliedsnummer mit).
    const openRecalculationErrorsDialog = (errors) => {
        const dialog = element('dialog', {className: 'activity-dialog'});
        const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
        const closeButton = element('button', {className: 'secondary-button', text: 'Schließen', attributes: {type: 'button'}});
        const content = element('div', {className: 'activity-dialog-content', children: [
            element('header', {children: [
                element('div', {children: [
                    element('p', {className: 'eyebrow', text: 'Mitglieder'}),
                    element('h2', {text: 'Nicht neu berechnete Mitglieder'}),
                ]}),
            ]}),
            element('p', {className: 'field-hint', text: `${errors.length} Mitglied(er) konnten nicht neu berechnet werden und blieben unverändert:`}),
            element('ul', {className: 'member-remarks', children: errors.map((error) => element('li', {children: [
                element('strong', {text: error.memberNumber}),
                element('p', {text: error.message}),
            ]}))}),
            element('div', {className: 'confirm-dialog-actions', children: [closeButton]}),
        ]});
        closeButton.addEventListener('click', () => dialog.close());
        close.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => dialog.remove());
        dialog.append(close, content);
        document.body.append(dialog);
        dialog.showModal();
    };

    let memberSearchTerm = '';
    let memberSortField = 'memberNumber';
    let memberSortDirection = 'asc';
    let memberStatusFilter = '';

    // Laut Beitrags- und Kassenordnung ist eine Kündigung nur fristgemäß zum Jahresende möglich —
    // ein gesetztes Austrittsdatum liegt praktisch immer auf den 31.12. des jeweiligen Jahres (siehe
    // auch `GetMembershipDashboardQuery::$leavingAtYearEnd`/`$leftLastYearEnd`, dieselbe Definition).
    const isLeavingAtYearEnd = (leftAt) => !!leftAt && leftAt === `${new Date().getFullYear()}-12-31`;
    const isLeftLastYearEnd = (leftAt) => !!leftAt && leftAt === `${new Date().getFullYear() - 1}-12-31`;
    const showMembers = async () => {
        const query = memberSearchTerm ? `?search=${encodeURIComponent(memberSearchTerm)}` : '';
        const data = await request('/api/admin/v1/members' + query);
        const heading = sectionHeading('Mitglieder', 'Stammdaten aller Vereinsmitglieder verwalten');
        const actions = [];
        if (canEditModule('members')) {
            const create = element('button', {className: 'button', text: '＋ Neues Mitglied', attributes: {type: 'button'}});
            create.addEventListener('click', () => openMemberDialog(null, showMembershipManagement));
            actions.push(create);
        }
        if (canEditModule('members')) {
            // Bewusst kein generischer `actionButton`: der Antwortkörper enthält je übersprungenem
            // Mitglied dessen Mitgliedsnummer und den Grund (`errors`) — die müssen sichtbar
            // gemacht werden, statt wie bei `actionButton` ungelesen zu verfallen.
            const recalculateAll = element('button', {className: 'secondary-button', text: 'Beiträge für alle Mitglieder neu berechnen', attributes: {type: 'button'}});
            recalculateAll.addEventListener('click', async () => {
                const confirmed = await confirmAction(
                    'Beiträge neu berechnen',
                    'Der Beitrag wird für alle Mitglieder anhand der aktuellen Beitragssätze und Altersspannen neu berechnet. Das kann nicht rückgängig gemacht werden.',
                    'Neu berechnen',
                );
                if (!confirmed) return;
                recalculateAll.disabled = true;
                try {
                    const result = await request('/api/admin/v1/members/recalculate-contributions', {method: 'POST'});
                    if (result.errors.length) {
                        toast(`${result.updated} Mitglied(er) aktualisiert, ${result.errors.length} übersprungen — siehe Liste.`, 'info');
                        openRecalculationErrorsDialog(result.errors);
                    } else {
                        toast(`Die Beiträge wurden für alle ${result.updated} Mitglieder neu berechnet.`);
                    }
                    await showMembershipManagement();
                } catch (error) {
                    toast(error.message, 'error');
                } finally {
                    recalculateAll.disabled = false;
                }
            });
            actions.push(recalculateAll);
        }
        if (canEditModule('members')) {
            actions.push(actionMenu('Datenbank', [
                {label: 'Importieren …', run: () => openMemberImportDialog(showMembershipManagement)},
                {label: 'Exportieren …', run: () => openMemberExportDialog()},
            ]));
        }


        const search = searchField('Volltextsuche: Nummer, Name, Straße, PLZ, Ort, E-Mail, Telefon …', memberSearchTerm, async (value) => {
            memberSearchTerm = value;
            await showMembershipManagement();
        });

        const statusFilter = element('select', {attributes: {'aria-label': 'Nach Status filtern'}, children: [
            element('option', {text: 'Alle', attributes: {value: ''}}),
            element('option', {text: 'Aktiv', attributes: {value: 'active'}}),
            element('option', {text: 'Inaktiv', attributes: {value: 'inactive'}}),
            element('option', {text: 'Austritte zum Jahresende', attributes: {value: 'leaving_year_end'}}),
            element('option', {text: 'Austritte aus Vorjahr', attributes: {value: 'left_last_year_end'}}),
        ]});
        statusFilter.value = memberStatusFilter;
        statusFilter.addEventListener('change', async () => {
            memberStatusFilter = statusFilter.value;
            await showMembershipManagement();
        });

        const filteredItems = data.items.filter((memberItem) => {
            if (memberStatusFilter === 'active') return memberItem.active;
            if (memberStatusFilter === 'inactive') return !memberItem.active;
            if (memberStatusFilter === 'leaving_year_end') return isLeavingAtYearEnd(memberItem.leftAt);
            if (memberStatusFilter === 'left_last_year_end') return isLeftLastYearEnd(memberItem.leftAt);

            return true;
        });

        const columns = [
            {key: 'memberNumber', label: 'Mitgl.-Nr.'},
            {key: 'primaryMemberNumber', label: 'Hauptnr.'},
            {key: 'salutation', label: 'Anrede'},
            {key: 'lastName', label: 'Name'},
            {key: 'firstName', label: 'Vorname'},
            {key: 'birthDate', label: 'Geburtsdatum'},
            {key: 'street', label: 'Straße'},
            {key: 'postalCode', label: 'PLZ'},
            {key: 'city', label: 'Ort'},
        ];
        const compareMemberValues = (left, right) => {
            if (left == null && right == null) return 0;
            if (left == null) return -1;
            if (right == null) return 1;

            return String(left).localeCompare(String(right), 'de', {numeric: true, sensitivity: 'base'});
        };

        const buildTable = () => {
            const sortedItems = [...filteredItems].sort((left, right) => {
                const direction = memberSortDirection === 'desc' ? -1 : 1;

                return compareMemberValues(left[memberSortField], right[memberSortField]) * direction;
            });
            const rows = sortedItems.map((memberItem) => {
                const row = element('tr', {
                    className: 'member-row',
                    attributes: {tabindex: '0', role: 'button'},
                    children: [
                        memberItem.memberNumber,
                        memberItem.primaryMemberNumber,
                        SALUTATION_LABELS[memberItem.salutation] || memberItem.salutation,
                        memberItem.lastName,
                        memberItem.firstName,
                        new Date(`${memberItem.birthDate}T00:00:00`).toLocaleDateString('de-DE'),
                        memberItem.street,
                        memberItem.postalCode,
                        memberItem.city,
                    ].map((text) => element('td', {text})),
                });
                const open = () => openMemberDialog(memberItem, showMembershipManagement);
                row.addEventListener('click', open);
                row.addEventListener('keydown', (event) => { if (event.key === 'Enter') open(); });

                return row;
            });
            const headerCells = columns.map((column) => {
                const isActive = column.key === memberSortField;
                const arrow = isActive ? (memberSortDirection === 'asc' ? ' ▲' : ' ▼') : '';
                const th = element('th', {
                    className: 'sortable-column',
                    text: `${column.label}${arrow}`,
                    attributes: {
                        tabindex: '0',
                        role: 'button',
                        'aria-sort': isActive ? (memberSortDirection === 'asc' ? 'ascending' : 'descending') : 'none',
                    },
                });
                const toggleSort = () => {
                    if (memberSortField === column.key) {
                        memberSortDirection = memberSortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        memberSortField = column.key;
                        memberSortDirection = 'asc';
                    }
                    const newTable = buildTable();
                    table.replaceWith(newTable);
                    table = newTable;
                };
                th.addEventListener('click', toggleSort);
                th.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ' ') return;
                    event.preventDefault();
                    toggleSort();
                });

                return th;
            });

            return element('table', {className: 'data-table', children: [
                element('thead', {children: [element('tr', {children: headerCells})]}),
                element('tbody', {children: rows}),
            ]});
        };
        let table = buildTable();

        workspace.replaceChildren(
            element('div', {className: 'management-header', children: [heading, element('div', {className: 'management-actions', children: actions})]}),
            element('div', {className: 'management-toolbar', children: [search, statusFilter, element('span', {text: memberStatusFilter
                ? `${filteredItems.length} von ${data.total} Mitglieder`
                : `${data.total} Mitglieder`})]}),
            filteredItems.length
                ? table
                : emptyState(data.items.length ? 'Keine Mitglieder für diesen Filter.' : 'Noch keine Mitglieder angelegt.'),
        );
    };

    const openContributionRateDialog = (rate, existingRates, onSaved, settings) => {
        const suffix = rate?.id || 'new';
        const dialog = element('dialog', {className: 'activity-dialog'});
        const usedCategories = new Set(existingRates.map((item) => item.category).filter(Boolean));
        const category = !rate ? selectField('Kategorie', `rate-category-${suffix}`, [
            ['', 'Keine (benutzerdefiniert)'],
            ...Object.entries(CONTRIBUTION_CATEGORY_LABELS).filter(([value]) => !usedCategories.has(value)),
        ], '') : null;
        if (category) {
            category.append(element('small', {text: 'Eine der sechs festen Kategorien nur wählen, um sie nach einem Löschen neu anzulegen – sie wird sonst nicht in der automatischen Beitragsermittlung berücksichtigt.'}));
        }
        const label = field('Bezeichnung', `rate-label-${suffix}`, rate?.label || '');
        const amount = field('Betrag (Euro)', `rate-amount-${suffix}`, rate ? (rate.amountCents / 100).toFixed(2) : '0.00', 'number');
        amount.querySelector('input').step = '0.01';
        amount.querySelector('input').min = '0';
        const period = selectField('Zeitraum', `rate-period-${suffix}`, Object.entries(PAYMENT_INTERVAL_LABELS), rate?.period || 'yearly');
        const personGroup = selectField('Personenkreis', `rate-person-group-${suffix}`, [
            ['', 'Keiner (gilt für alle)'],
            ...Object.entries(PERSON_GROUP_LABELS),
        ], rate?.personGroup || '');
        personGroup.append(element('small', {text: 'Bei Zeitraum „Einmalig“ erforderlich – wird dann bei Neuanlage eines passenden Mitglieds automatisch berechnet (Familie nur beim Hauptmitglied).'}));
        const minAge = field('Altersspanne – von (Jahre, optional)', `rate-min-age-${suffix}`, rate?.minAge != null ? String(rate.minAge) : '', 'number');
        const maxAge = field('Altersspanne – bis einschließlich (Jahre, optional)', `rate-max-age-${suffix}`, rate?.maxAge != null ? String(rate.maxAge) : '', 'number');
        minAge.querySelector('input').min = '0';
        maxAge.querySelector('input').min = '0';

        const pending = rate?.pending || null;
        const validFrom = field('Gültig ab', `rate-valid-from-${suffix}`, settings.validFrom || '', 'date');
        validFrom.append(element('small', {className: 'field-hint', text: 'Ein zukünftiges Datum speichert alle Änderungen als Pending. Heute oder früher übernimmt sie sofort.'}));
        if (pending) {
            const plannedFields = [
                [label, 'label', pending.label],
                [amount, 'amountCents', formatEuro(pending.amountCents)],
                [period, 'period', PAYMENT_INTERVAL_LABELS[pending.period]],
                [personGroup, 'personGroup', PERSON_GROUP_LABELS[pending.personGroup] || 'Keiner (gilt für alle)'],
                [minAge, 'minAge', pending.minAge ?? 'Keine Untergrenze'],
                [maxAge, 'maxAge', pending.maxAge ?? 'Keine Obergrenze'],
                [validFrom, 'validFrom', formatDateDE(pending.validFrom)],
            ];
            plannedFields.forEach(([control, key, value]) => {
                if (key !== 'validFrom' && pending[key] === rate[key]) return;
                const hint = element('small', {className: 'field-hint', text: `wird geändert zu: ${value} ab ${formatDateDE(pending.validFrom)}`});
                hint.id = `${control.querySelector('input, select').id}-pending`;
                control.querySelector('input, select').setAttribute('aria-describedby', hint.id);
                control.append(hint);
            });
        }
        const message = formMessage();
        const submit = element('button', {className: 'button', text: rate ? 'Änderungen speichern' : 'Beitragssatz anlegen', attributes: {type: 'submit'}});
        const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
        const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
        const deleteButton = rate ? element('button', {className: 'secondary-button danger-button', text: 'Löschen', attributes: {type: 'button'}}) : null;
        if (deleteButton) {
            deleteButton.addEventListener('click', async () => {
                const warning = rate.category
                    ? `„${rate.label}“ wird von der automatischen Beitragsermittlung genutzt und endgültig gelöscht. Bis sie neu angelegt wird, schlägt die Berechnung für betroffene Mitglieder fehl.`
                    : `„${rate.label}“ wird endgültig gelöscht.`;
                const confirmed = await confirmAction('Beitragssatz löschen', warning, 'Löschen');
                if (!confirmed) return;
                deleteButton.disabled = true;
                try {
                    await request(`/api/admin/v1/contribution-rates/${rate.id}`, {method: 'DELETE'});
                    toast('Der Beitragssatz wurde gelöscht.');
                    dialog.close();
                    await onSaved();
                } catch (error) {
                    toast(error.message, 'error');
                    deleteButton.disabled = false;
                }
            });
        }
        const form = element('form', {className: 'activity-dialog-content', children: [
            element('header', {children: [
                element('div', {children: [
                    element('p', {className: 'eyebrow', text: rate ? 'Beitragssatz bearbeiten' : 'Neuer Beitragssatz'}),
                    element('h2', {text: rate ? (CONTRIBUTION_CATEGORY_LABELS[rate.category] || rate.label) : 'Beitragssatz anlegen'}),
                    ...(rate && !rate.category ? [element('small', {text: 'Benutzerdefiniert – nicht Teil der automatischen Beitragsermittlung.'})] : []),
                ]}),
            ]}),
            ...(category ? [category] : []),
            label, amount, period, personGroup, minAge, maxAge,
            validFrom,
            message,
            element('div', {className: 'confirm-dialog-actions', children: [...(deleteButton ? [deleteButton] : []), cancel, submit]}),
        ]});
        label.querySelector('input').required = true;
        cancel.addEventListener('click', () => dialog.close());
        close.addEventListener('click', () => dialog.close());
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            submit.disabled = true;
            const minAgeValue = minAge.querySelector('input').value;
            const maxAgeValue = maxAge.querySelector('input').value;
            const payload = {
                ...(category ? {category: category.querySelector('select').value || null} : {}),
                label: label.querySelector('input').value,
                amountCents: Math.round(Number.parseFloat(amount.querySelector('input').value) * 100),
                period: period.querySelector('select').value,
                personGroup: personGroup.querySelector('select').value || null,
                minAge: minAgeValue === '' ? null : Number.parseInt(minAgeValue, 10),
                maxAge: maxAgeValue === '' ? null : Number.parseInt(maxAgeValue, 10),

            };
            const effectiveDate = validFrom.querySelector('input').value;
            const unchanged = rate && ['label', 'amountCents', 'period', 'personGroup', 'minAge', 'maxAge'].every((key) => payload[key] === rate[key]);
            if (rate && effectiveDate && !(unchanged && effectiveDate === (settings.validFrom || ''))) {
                payload.validFrom = effectiveDate;
            } else if (pending) {
                payload.pending = pending;
            }
            try {
                await request(rate ? `/api/admin/v1/contribution-rates/${rate.id}` : '/api/admin/v1/contribution-rates', {
                    method: rate ? 'PUT' : 'POST',
                    body: JSON.stringify(payload),
                });
                toast(rate ? 'Der Beitragssatz wurde gespeichert.' : 'Der Beitragssatz wurde angelegt.');
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
    };

    /**
     * Das gemeinsame „gültig ab" für alle Beitragssätze (siehe `ContributionRateSettings`) — anders
     * als eine geplante Betragsänderung je Beitragssatz gilt dies für alle gemeinsam. Rückt
     * außerdem automatisch vor, sobald eine geplante Änderung greift (dann nur lesend sichtbar,
     * hier aber weiterhin von Hand überschreibbar).
     */
    const renderContributionRateSettingsCard = (settings, onSaved) => {
        const message = formMessage();
        const validFrom = field('Gültig ab (für alle Beitragssätze)', 'contribution-rate-settings-valid-from', settings.validFrom || '', 'date');
        const form = element('form', {className: 'compact-form contribution-rate-settings-card', children: [
            element('h3', {text: 'Beitragsordnung'}),
            element('p', {className: 'field-hint', text: 'Gilt für alle Beitragssätze gemeinsam — wird in „Meine Mitgliedschaft" bei der Gesamtberechnung angezeigt. Rückt automatisch vor, sobald eine geplante Betragsänderung (siehe einzelne Beitragssätze unten) greift.'}),
            validFrom,
            message,
            element('button', {className: 'button', text: 'Speichern', attributes: {type: 'submit'}}),
        ]});
        if (!canEditModule('contribution_rates')) {
            validFrom.querySelector('input').disabled = true;
            form.querySelector('button').hidden = true;
        }
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button');
            button.disabled = true;
            try {
                await request('/api/admin/v1/contribution-rate-settings', {
                    method: 'PUT',
                    body: JSON.stringify({validFrom: validFrom.querySelector('input').value || null}),
                });
                toast('Gespeichert.');
                await onSaved();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
            } finally {
                button.disabled = false;
            }
        });

        return form;
    };

    const showContributionRates = async () => {
        const data = await request('/api/admin/v1/contribution-rates');
        const settings = await request('/api/admin/v1/contribution-rate-settings');
        const heading = sectionHeading('Beitragssätze', 'Bezeichnung, Betrag, Zeitraum und Altersspanne je Beitragssatz pflegen');
        if (canEditModule('contribution_rates')) {
            const create = element('button', {className: 'button', text: '＋ Neuer Beitragssatz', attributes: {type: 'button'}});
            create.addEventListener('click', () => openContributionRateDialog(null, data.items, showMembershipManagement, settings));
            heading.append(create);
        }
        const rows = data.items.map((rate) => {
            const ageRange = rate.minAge != null || rate.maxAge != null
                ? `${rate.minAge ?? '0'}–${rate.maxAge ?? '∞'} Jahre`
                : null;
            const isOnce = rate.period === 'once';
            const row = element('button', {
                className: 'activity-list-row',
                attributes: {type: 'button', ...(canEditModule('contribution_rates') ? {} : {disabled: 'disabled'})},
                children: [
                    element('span', {className: 'activity-list-copy', children: [
                        element('strong', {text: CONTRIBUTION_CATEGORY_LABELS[rate.category] || rate.label}),
                        element('small', {text: [
                            rate.category ? rate.label : 'Benutzerdefiniert',
                            rate.personGroup ? PERSON_GROUP_LABELS[rate.personGroup] : null,
                            ageRange,
                            rate.pending ? `Änderung ab ${formatDateDE(rate.pending.validFrom)}` : null,
                        ].filter(Boolean).join(' · ')}),
                    ]}),
                    element('span', {className: 'status-badge', text: `${formatEuro(rate.amountCents)} · ${PAYMENT_INTERVAL_LABELS[rate.period] || rate.period}`}),
                    ...(rate.status === 'pending' ? [element('span', {className: 'status-badge status-pending', text: 'Pending'})] : []),
                    ...(isOnce ? [] : [element('span', {className: 'status-badge', text: `${formatEuro(rate.annualAmountCents)} / Jahr`})]),
                    ...(canEditModule('contribution_rates') ? [element('span', {className: 'activity-list-edit', text: 'Bearbeiten ›'})] : []),
                ],
            });
            if (canEditModule('contribution_rates')) row.addEventListener('click', () => openContributionRateDialog(rate, data.items, showMembershipManagement, settings));

            return row;
        });
        workspace.replaceChildren(
            heading,
            element('div', {className: 'activity-list', children: rows.length ? rows : [emptyState('Keine Beitragssätze vorhanden.')]}),
            renderContributionRateSettingsCard(settings, showContributionRates),
        );
    };

    const showMembershipManagement = async () => {
        const tabs = [
            ...(hasModule('members') ? [['dashboard', 'Dashboard', showMembershipDashboard]] : []),
            ...(hasModule('members') ? [['members', 'Mitglieder', showMembers]] : []),
            ...(hasModule('contribution_rates') ? [['rates', 'Beitragssätze', showContributionRates]] : []),
            ...(hasModule('membership_applications') ? [['applications', 'Mitgliedsanträge', showMembership]] : []),
            ...(hasModule('member_messages') ? [['messages', 'Mitgliedernachrichten', showMemberMessages]] : []),
        ];
        if (!tabs.some(([key]) => key === activeMembershipTab)) activeMembershipTab = tabs[0]?.[0] || null;
        if (activeMembershipTab) {
            setAdminPath(['mitglieder', membershipSlugsByTab[activeMembershipTab]], true);
        }

        const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'Mitgliederverwaltung'}, children: tabs.map(([key, label]) => {
            const button = element('a', {
                className: `sub-tab${key === activeMembershipTab ? ' active' : ''}`,
                text: label,
                attributes: {href: adminPath('mitglieder', membershipSlugsByTab[key])},
            });
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                activeMembershipTab = key;
                setAdminPath(['mitglieder', membershipSlugsByTab[key]]);
                await showMembershipManagement();
            });

            return button;
        })});
        const active = tabs.find(([key]) => key === activeMembershipTab);
        if (!active) {
            workspace.replaceChildren(tabStrip, emptyState('Für diesen Zugang ist kein Bereich der Mitgliederverwaltung freigeschaltet.'));
            return;
        }
        // showMembers/showMembershipDashboard/showContributionRates/showMembership schreiben wie
        // jedes andere Modul direkt in `workspace`. Deren Ergebnis wird danach in ein Tab-Panel
        // umgehängt, damit Tab-Leiste und Inhalt gemeinsam sichtbar bleiben.
        await active[2]();
        const panel = element('div', {className: 'sub-tab-panel', children: [...workspace.children]});
        workspace.replaceChildren(tabStrip, panel);
    };

    // Reiter „PIN-Schutzverwaltung“ im Modul „Einstellungen“ (siehe `showSettingsManagement`), nur
    // für Admin/Super-Admin erreichbar (siehe `isGlobalAdministrator()` dort) — hier lassen sich der
    // gemeinsame PIN sowie die damit geschützten Module/Funktionen verwalten (`ProtectedAction`,
    // serverseitig durchgesetzt in `AdminPinSettingsController` bzw. an der jeweils geschützten
    // Stelle wie `AdminMemberController::delete()`).
    const showPinProtection = async () => {
        const data = await request('/api/admin/v1/pin-settings');
        const message = formMessage();

        const pinField = field(data.globalPinIsSet ? 'Neuer globaler PIN (4–8 Ziffern)' : 'Globaler PIN (4–8 Ziffern)', 'settings-pin', '', 'password');
        const pinInput = pinField.querySelector('input');
        pinInput.inputMode = 'numeric';
        pinInput.autocomplete = 'off';
        pinInput.maxLength = 8;
        const setPin = element('button', {className: 'button', text: data.globalPinIsSet ? 'Globalen PIN ändern' : 'Globalen PIN festlegen', attributes: {type: 'button'}});
        setPin.addEventListener('click', async () => {
            const pin = pinInput.value.trim();
            if (!pin) return;
            setPin.disabled = true;
            try {
                await request('/api/admin/v1/pin-settings/pin', {method: 'PUT', body: JSON.stringify({pin})});
                toast(data.globalPinIsSet ? 'Der globale PIN wurde geändert.' : 'Der globale PIN wurde festgelegt.');
                await showPinProtection();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
                setPin.disabled = false;
            }
        });

        // Je Aktion eigener, vom globalen PIN abweichender PIN — optional; ohne eigenen PIN gilt
        // der globale (siehe `PinSettings::matchesPin()`). Klick öffnet denselben PIN-Dialog wie
        // die Ausführung geschützter Aktionen, hier aber zum *Festlegen* statt zum Prüfen.
        const setOwnPin = async (action) => {
            const unlocked = await promptForPin(
                action.hasOwnPin ? `Eigenen PIN für „${action.label}“ ändern` : `Eigenen PIN für „${action.label}“ festlegen`,
                'Gilt danach nur noch für diesen Eintrag, statt des globalen PIN.',
                (pin) => request(`/api/admin/v1/pin-settings/actions/${action.key}/pin`, {method: 'PUT', body: JSON.stringify({pin})}),
            );
            if (unlocked) {
                toast(`Eigener PIN für „${action.label}“ gespeichert.`);
                await showPinProtection();
            }
        };
        const clearOwnPin = async (action) => {
            const confirmed = await confirmAction(
                'Eigenen PIN entfernen',
                `„${action.label}“ verwendet danach wieder den globalen PIN.`,
                'Entfernen',
            );
            if (!confirmed) return;
            try {
                await request(`/api/admin/v1/pin-settings/actions/${action.key}/pin`, {method: 'DELETE'});
                toast(`Eigener PIN für „${action.label}“ entfernt.`);
                await showPinProtection();
            } catch (error) {
                toast(error.message, 'error');
            }
        };

        const entries = data.availableActions.map((action) => {
            const checkbox = element('input', {attributes: {type: 'checkbox', value: action.key}});
            checkbox.checked = data.protectedActions.includes(action.key);

            const ownPinButton = element('button', {className: 'secondary-button', text: action.hasOwnPin ? 'Eigenen PIN ändern' : 'Eigenen PIN festlegen', attributes: {type: 'button'}});
            ownPinButton.addEventListener('click', () => setOwnPin(action));
            const clearOwnPinButton = action.hasOwnPin
                ? element('button', {className: 'text-button', text: 'Eigenen PIN entfernen', attributes: {type: 'button'}})
                : null;
            if (clearOwnPinButton) clearOwnPinButton.addEventListener('click', () => clearOwnPin(action));

            const row = element('div', {className: 'protected-action-row', children: [
                element('label', {className: 'check-field', children: [checkbox, element('span', {text: action.label})]}),
                element('span', {className: 'field-hint', text: action.hasOwnPin ? 'Eigener PIN gesetzt' : 'Nutzt globalen PIN'}),
                ownPinButton,
                ...(clearOwnPinButton ? [clearOwnPinButton] : []),
            ]});

            return {action, row, checkbox};
        });
        const byCategory = new Map();
        entries.forEach((entry) => {
            if (!byCategory.has(entry.action.category)) byCategory.set(entry.action.category, []);
            byCategory.get(entry.action.category).push(entry.row);
        });
        const groups = [...byCategory.entries()].map(([category, rows]) => element('fieldset', {children: [
            element('legend', {text: category}),
            ...rows,
        ]}));
        const saveProtectedActions = element('button', {className: 'button', text: 'Auswahl speichern', attributes: {type: 'button'}});
        saveProtectedActions.addEventListener('click', async () => {
            const protectedActions = entries.filter((entry) => entry.checkbox.checked).map((entry) => entry.action.key);
            saveProtectedActions.disabled = true;
            try {
                await request('/api/admin/v1/pin-settings/protected-actions', {method: 'PUT', body: JSON.stringify({protectedActions})});
                toast('Der PIN-Schutz wurde aktualisiert.');
                await showPinProtection();
            } catch (error) {
                toast(error.message, 'error');
                saveProtectedActions.disabled = false;
            }
        });

        workspace.replaceChildren(
            sectionHeading('PIN-Schutzverwaltung', 'Global oder je Modul/Funktion einen eigenen PIN vergeben'),
            element('div', {className: 'card-list', children: [
                element('article', {className: 'management-card', children: [
                    element('h3', {text: 'Globaler PIN'}),
                    element('p', {className: 'field-hint', text: data.globalPinIsSet
                        ? 'Ein globaler PIN ist hinterlegt und gilt als Rückfall für jeden geschützten Eintrag ohne eigenen PIN. Beim Ändern wird der alte PIN sofort ungültig.'
                        : 'Es ist noch kein globaler PIN hinterlegt. Module/Funktionen ohne eigenen PIN lassen sich erst danach schützen.'}),
                    pinField, setPin, message,
                ]}),
                element('article', {className: 'management-card', children: [
                    element('h3', {text: 'Geschützte Module & Funktionen'}),
                    element('p', {className: 'field-hint', text: 'Für ausgewählte Einträge wird der PIN zusätzlich zu den regulären Berechtigungen abgefragt — je Eintrag entweder der globale PIN oder ein eigener, davon abweichender.'}),
                    ...(groups.length ? groups : [emptyState('Keine schützbaren Module/Funktionen bekannt.')]),
                    saveProtectedActions,
                ]}),
            ]}),
        );
    };

    // Reiter „E-Mail-Einstellungen“ innerhalb von „E-Mail-Einstellungen“ (siehe
    // `showEmailSettingsManagement`) — hier werden die SMTP-Zugangsdaten für den Mailversand sowie,
    // je `NotificationEvent`, die zu benachrichtigenden Empfänger gepflegt
    // (`AdminEmailSettingsController`). Erstes Beispiel eines Ereignisses: ein neuer
    // Mitgliedsantrag (siehe `SubmitMembershipApplicationUseCase`). Die Texte der versendeten
    // Mails selbst stehen nicht hier, sondern im Nachbar-Reiter „Mailvorlagen“
    // (`showMailTemplates`).
    const showEmailConnectionSettings = async () => {
        const data = await request('/api/admin/v1/email-settings');
        const message = formMessage();

        const providerSelect = selectField('Anbieter', 'email-provider', [
            ['', '– Bitte wählen –'],
            ...data.providerPresets.map((preset) => [preset.key, preset.label]),
        ], data.provider || '');
        const providerSelectInput = providerSelect.querySelector('select');
        const host = field('SMTP-Server', 'email-host', data.host || '');
        const hostInput = host.querySelector('input');
        const port = field('Port', 'email-port', data.port ?? '', 'number');
        const portInput = port.querySelector('input');
        const username = field('Benutzername', 'email-username', data.username || '');
        const password = field(data.passwordIsSet ? 'Neues Passwort (leer lassen zum Beibehalten)' : 'Passwort', 'email-password', '', 'password');
        const passwordInput = password.querySelector('input');
        passwordInput.autocomplete = 'off';
        const fromAddress = field('Absender-E-Mail-Adresse', 'email-from-address', data.fromAddress || '', 'email');
        const fromName = field('Absender-Name (optional)', 'email-from-name', data.fromName || '');

        const providerHint = element('p', {className: 'field-hint'});
        const applyProviderHint = () => {
            const preset = data.providerPresets.find((entry) => entry.key === providerSelectInput.value);
            providerHint.textContent = preset ? preset.hint : '';
        };
        providerSelectInput.addEventListener('change', () => {
            // Bewusst überschreiben (nicht nur bei leerem Feld vorbelegen): Der Sinn der
            // Preset-Auswahl ist gerade, Host/Port auf die bekannt richtigen Werte für den
            // gewählten Anbieter umzustellen — auch wenn zuvor schon ein anderer Anbieter
            // gespeichert war. Zugangsdaten/Absender bleiben davon unberührt.
            const preset = data.providerPresets.find((entry) => entry.key === providerSelectInput.value);
            if (preset) {
                if (preset.defaultHost) hostInput.value = preset.defaultHost;
                if (preset.defaultPort) portInput.value = String(preset.defaultPort);
            }
            applyProviderHint();
        });
        applyProviderHint();

        const save = element('button', {className: 'button', text: 'Speichern', attributes: {type: 'button'}});
        save.addEventListener('click', async () => {
            save.disabled = true;
            try {
                await request('/api/admin/v1/email-settings', {method: 'PUT', body: JSON.stringify({
                    provider: providerSelectInput.value || null,
                    host: hostInput.value || null,
                    port: portInput.value ? Number.parseInt(portInput.value, 10) : null,
                    username: username.querySelector('input').value || null,
                    password: passwordInput.value || null,
                    fromAddress: fromAddress.querySelector('input').value || null,
                    fromName: fromName.querySelector('input').value || null,
                })});
                toast('Die E-Mail-Einstellungen wurden gespeichert.');
                await showSettingsManagement();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
                save.disabled = false;
            }
        });

        const testTo = field('Testmail senden an', 'email-test-to', '', 'email');
        const testButton = element('button', {className: 'secondary-button', text: 'Testmail senden', attributes: {type: 'button'}});
        testButton.addEventListener('click', async () => {
            const to = testTo.querySelector('input').value.trim();
            if (!to) return;
            testButton.disabled = true;
            try {
                await request('/api/admin/v1/email-settings/test', {method: 'POST', body: JSON.stringify({to})});
                toast('Die Testmail wurde gesendet.');
            } catch (error) {
                toast(error.message, 'error');
            } finally {
                testButton.disabled = false;
            }
        });

        const notificationRows = data.notificationEvents.map((event) => {
            const currentRecipients = (data.notificationRecipients[event.key] || []).join(', ');
            const recipientsField = field(event.label, `email-recipients-${event.key}`, currentRecipients);
            const saveRecipients = element('button', {className: 'secondary-button', text: 'Speichern', attributes: {type: 'button'}});
            saveRecipients.addEventListener('click', async () => {
                const recipients = recipientsField.querySelector('input').value.split(',').map((value) => value.trim()).filter(Boolean);
                saveRecipients.disabled = true;
                try {
                    await request(`/api/admin/v1/email-settings/notifications/${event.key}`, {method: 'PUT', body: JSON.stringify({recipients})});
                    toast('Die Empfänger wurden gespeichert.');
                } catch (error) {
                    toast(error.message, 'error');
                } finally {
                    saveRecipients.disabled = false;
                }
            });

            return element('div', {className: 'protected-action-row', children: [recipientsField, saveRecipients]});
        });

        workspace.replaceChildren(
            sectionHeading('E-Mail-Einstellungen', 'Mailversand konfigurieren und Empfänger für automatische Benachrichtigungen festlegen'),
            element('div', {className: 'card-list', children: [
                element('article', {className: 'management-card', children: [
                    element('h3', {text: 'Mailserver'}),
                    ...(data.configured ? [] : [element('p', {className: 'field-hint', text: 'Noch nicht vollständig konfiguriert — mindestens Server und Absender-E-Mail-Adresse sind erforderlich.'})]),
                    fieldRow([providerSelect, host]),
                    fieldRow([port, username]),
                    password,
                    fieldRow([fromAddress, fromName]),
                    providerHint,
                    save, message,
                ]}),
                element('article', {className: 'management-card', children: [
                    element('h3', {text: 'Testmail'}),
                    testTo,
                    testButton,
                ]}),
                element('article', {className: 'management-card', children: [
                    element('h3', {text: 'Benachrichtigungen'}),
                    element('p', {className: 'field-hint', text: 'Mehrere Adressen mit Komma trennen. Leer lassen, um für dieses Ereignis keine Benachrichtigung zu versenden.'}),
                    ...notificationRows,
                ]}),
            ]}),
        );
    };

    // Zeigt Betreff/HTML/Text einer mit Beispieldaten gerenderten Mailvorlage (siehe
    // `PreviewMailTemplateUseCase`) in einem eigenen Dialog. Die HTML-Ansicht läuft in einem
    // sandboxed <iframe> mit eigenem <html>/<head> (siehe `BrandedEmailLayout`) statt im DOM der
    // Seite selbst, da die Mail ihre eigenen, teils widersprüchlichen Inline-Styles mitbringt.
    const openMailTemplatePreview = (label, preview) => {
        const dialog = element('dialog', {className: 'preview-dialog'});
        const htmlFrame = element('iframe', {
            className: 'mail-preview-frame',
            attributes: {title: `Vorschau: ${preview.subject}`, sandbox: '', srcdoc: preview.html},
        });
        const textFrame = element('pre', {className: 'mail-preview-text', text: preview.text});
        textFrame.hidden = true;

        const htmlToggle = element('button', {className: 'editor-tool active', text: 'HTML-Ansicht', attributes: {type: 'button'}});
        const textToggle = element('button', {className: 'editor-tool', text: 'Text-Ansicht', attributes: {type: 'button'}});
        const setView = (view) => {
            htmlFrame.hidden = view !== 'html';
            textFrame.hidden = view !== 'text';
            htmlToggle.classList.toggle('active', view === 'html');
            textToggle.classList.toggle('active', view === 'text');
        };
        htmlToggle.addEventListener('click', () => setView('html'));
        textToggle.addEventListener('click', () => setView('text'));

        const close = element('button', {className: 'secondary-button', text: 'Vorschau schließen', attributes: {type: 'button'}});
        close.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => dialog.remove());
        dialog.append(
            element('header', {className: 'preview-toolbar', children: [
                element('strong', {text: `Vorschau: ${label}`}),
                element('div', {className: 'preview-actions', children: [htmlToggle, textToggle, close]}),
            ]}),
            element('div', {className: 'preview-stage', children: [htmlFrame, textFrame]}),
        );
        document.body.append(dialog);
        dialog.showModal();
    };

    // Reiter „Mailvorlagen“ innerhalb von „E-Mail-Einstellungen“ (siehe
    // `showEmailSettingsManagement`) — die redaktionell pflegbaren Texte hinter jeder automatisch
    // versendeten E-Mail (`MailTemplateKey`), statt sie im Code zu pflegen. Erstes Beispiel: die
    // Bestätigungsmail bei einem angenommenen Mitgliedsantrag (`ReleaseMembershipApplicationUseCase`).
    const showMailTemplates = async () => {
        const [data, signatureData] = await Promise.all([
            request('/api/admin/v1/mail-templates'),
            request('/api/admin/v1/mail-signatures'),
        ]);
        const signatures = signatureData.items;

        const cards = data.items.map((template) => {
            const message = formMessage();
            const subject = field('Betreff', `mail-template-subject-${template.key}`, template.subject);
            const body = field('Text', `mail-template-body-${template.key}`, template.body, 'textarea');
            const bodyInput = body.querySelector('textarea');
            bodyInput.rows = 10;

            // Die Signatur wird nur referenziert (nicht als Text kopiert, siehe
            // `MailTemplate::$signatureId`) — eine spätere Änderung der Signatur wirkt sich so
            // automatisch auf jede Vorlage aus, die sie zugeordnet hat, ohne diese einzeln
            // anzupassen. Beim Versand/in der Vorschau wird sie an den Text angehängt (siehe
            // `MailTemplateRenderer`).
            const signatureSelect = selectField('Signatur', `mail-template-signature-${template.key}`, [
                ['', 'Keine'],
                ...signatures.map((signature) => [signature.id, signature.name]),
            ], template.signatureId || '');
            const signatureSelectInput = signatureSelect.querySelector('select');

            const preview = element('button', {className: 'secondary-button', text: 'Vorschau', attributes: {type: 'button'}});
            preview.addEventListener('click', async () => {
                preview.disabled = true;
                try {
                    const result = await request(`/api/admin/v1/mail-templates/${template.key}/preview`, {method: 'POST', body: JSON.stringify({
                        subject: subject.querySelector('input').value,
                        body: bodyInput.value,
                        signatureId: signatureSelectInput.value || null,
                    })});
                    openMailTemplatePreview(template.label, result);
                } catch (error) {
                    toast(error.message, 'error');
                } finally {
                    preview.disabled = false;
                }
            });

            const save = element('button', {className: 'button', text: 'Speichern', attributes: {type: 'button'}});
            save.addEventListener('click', async () => {
                save.disabled = true;
                try {
                    await request(`/api/admin/v1/mail-templates/${template.key}`, {method: 'PUT', body: JSON.stringify({
                        subject: subject.querySelector('input').value,
                        body: bodyInput.value,
                        signatureId: signatureSelectInput.value || null,
                    })});
                    toast('Die Mailvorlage wurde gespeichert.');
                    await showSettingsManagement();
                } catch (error) {
                    message.textContent = error.message;
                    toast(error.message, 'error');
                    save.disabled = false;
                }
            });
            const reset = element('button', {className: 'secondary-button', text: 'Auf Standard zurücksetzen', attributes: {type: 'button'}});
            reset.addEventListener('click', async () => {
                const confirmed = await confirmAction(
                    'Auf Standard zurücksetzen',
                    `„${template.label}“ wird auf den mitgelieferten Standardtext zurückgesetzt (auch die Signatur-Zuordnung). Eigene Anpassungen gehen dabei verloren.`,
                    'Zurücksetzen',
                );
                if (!confirmed) return;
                try {
                    await request(`/api/admin/v1/mail-templates/${template.key}/reset`, {method: 'POST'});
                    toast('Die Mailvorlage wurde zurückgesetzt.');
                    await showSettingsManagement();
                } catch (error) {
                    toast(error.message, 'error');
                }
            });

            return element('article', {className: 'management-card', children: [
                element('h3', {text: template.label}),
                element('p', {className: 'field-hint', text: template.description}),
                element('p', {className: 'field-hint', text: `Verfügbare Platzhalter: ${template.placeholders.map((name) => `{{${name}}}`).join(', ')}`}),
                subject,
                body,
                signatureSelect,
                element('p', {className: 'field-hint', text: 'Die Signatur wird beim Versand automatisch an den Text angehängt — eine spätere Änderung an ihr wirkt sich auf jede Vorlage aus, die sie zugeordnet hat, ohne diese einzeln anzupassen. Verwaltung unter „Signaturen“.'}),
                element('p', {className: 'field-hint', text: 'Die Mail wird zusätzlich als gestaltete HTML-Ansicht im Design des Vereins verschickt — Leerzeilen im Text werden dabei zu Absätzen.'}),
                element('div', {className: 'confirm-dialog-actions', children: template.isDefault ? [preview, save] : [preview, save, reset]}),
                message,
            ]});
        });

        workspace.replaceChildren(
            sectionHeading('Mailvorlagen', 'Betreff und Text der automatisch versendeten E-Mails bearbeiten, statt sie im Code zu pflegen'),
            element('div', {className: 'card-list', children: cards}),
        );
    };

    // Reiter „Signaturen“ innerhalb von „E-Mail-Einstellungen“ (siehe `showEmailSettingsManagement`)
    // — wiederverwendbare Signaturen (z. B. „Freundliche Grüße / Das Waldbad-Team / …“), die einer
    // Mailvorlage (`showMailTemplates`) zugeordnet statt in deren Text kopiert werden: eine
    // Änderung hier wirkt sich automatisch auf jede zugeordnete Vorlage aus (siehe
    // `MailTemplate::$signatureId`, `MailTemplateRenderer`). Kann wie der übrige Vorlagentext
    // `{{name}}`-Platzhalter enthalten — die werden erst beim Versand der jeweiligen Vorlage ersetzt.
    const showMailSignatures = async () => {
        const data = await request('/api/admin/v1/mail-signatures');

        const signatureCard = (signature) => {
            const message = formMessage();
            const name = field('Name', `mail-signature-name-${signature?.id || 'new'}`, signature?.name || '');
            const body = field('Text', `mail-signature-body-${signature?.id || 'new'}`, signature?.body || '', 'textarea');
            body.querySelector('textarea').rows = 6;

            const save = element('button', {className: 'button', text: signature ? 'Speichern' : 'Anlegen', attributes: {type: 'button'}});
            save.addEventListener('click', async () => {
                save.disabled = true;
                try {
                    const payload = JSON.stringify({
                        name: name.querySelector('input').value,
                        body: body.querySelector('textarea').value,
                    });
                    if (signature) {
                        await request(`/api/admin/v1/mail-signatures/${signature.id}`, {method: 'PUT', body: payload});
                        toast('Die Signatur wurde gespeichert.');
                    } else {
                        await request('/api/admin/v1/mail-signatures', {method: 'POST', body: payload});
                        toast('Die Signatur wurde angelegt.');
                    }
                    await showSettingsManagement();
                } catch (error) {
                    message.textContent = error.message;
                    toast(error.message, 'error');
                    save.disabled = false;
                }
            });

            const actions = [save];
            if (signature) {
                const remove = element('button', {className: 'button danger-button', text: 'Löschen', attributes: {type: 'button'}});
                remove.addEventListener('click', async () => {
                    const confirmed = await confirmAction(
                        'Signatur löschen',
                        `„${signature.name}“ wird gelöscht. Bereits in Mailvorlagen eingefügte Signaturtexte bleiben davon unberührt.`,
                    );
                    if (!confirmed) return;
                    try {
                        await request(`/api/admin/v1/mail-signatures/${signature.id}`, {method: 'DELETE'});
                        toast('Die Signatur wurde gelöscht.');
                        await showSettingsManagement();
                    } catch (error) {
                        toast(error.message, 'error');
                    }
                });
                actions.unshift(remove);
            }

            return element('article', {className: 'management-card', children: [
                element('h3', {text: signature ? signature.name : 'Neue Signatur'}),
                name,
                body,
                element('div', {className: 'confirm-dialog-actions', children: actions}),
                message,
            ]});
        };

        workspace.replaceChildren(
            sectionHeading('Signaturen', 'Wiederverwendbare Signaturen, die Mailvorlagen zugeordnet werden können, statt sie in jede Vorlage zu kopieren'),
            element('p', {className: 'field-hint', text: 'Kann Platzhalter wie {{vereinsname}} enthalten. Welche Mailvorlage welche Signatur verwendet, wird im Reiter „Mailvorlagen“ je Vorlage ausgewählt — eine Änderung hier wirkt sich automatisch auf jede zugeordnete Vorlage aus.'}),
            element('div', {className: 'card-list', children: [...data.items.map(signatureCard), signatureCard(null)]}),
        );
    };

    // Modul „E-Mail-Einstellungen“ (Reiter innerhalb von „Einstellungen“, siehe
    // `showSettingsManagement`): bündelt Mailserver-Zugangsdaten/Benachrichtigungsempfänger
    // (`showEmailConnectionSettings`) und die Mailvorlagen-Texte (`showMailTemplates`) in eigenen
    // Unter-Reitern, analog zu `showSettingsManagement` selbst.
    const showEmailSettingsManagement = async () => {
        const tabs = [
            ['connection', 'E-Mail-Einstellungen', showEmailConnectionSettings],
            ['templates', 'Mailvorlagen', showMailTemplates],
            ['signatures', 'Signaturen', showMailSignatures],
        ];
        if (!tabs.some(([key]) => key === activeEmailSettingsTab)) activeEmailSettingsTab = tabs[0][0];
        setAdminPath(['einstellungen', 'e-mail', emailSlugsByTab[activeEmailSettingsTab]], true);

        const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'E-Mail-Einstellungen'}, children: tabs.map(([key, label]) => {
            const button = element('a', {
                className: `sub-tab${key === activeEmailSettingsTab ? ' active' : ''}`,
                text: label,
                attributes: {href: adminPath('einstellungen', 'e-mail', emailSlugsByTab[key])},
            });
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                activeEmailSettingsTab = key;
                setAdminPath(['einstellungen', 'e-mail', emailSlugsByTab[key]]);
                await showSettingsManagement();
            });

            return button;
        })});
        const active = tabs.find(([key]) => key === activeEmailSettingsTab);
        // showEmailConnectionSettings/showMailTemplates schreiben wie jedes andere Modul direkt in
        // `workspace`. Deren Ergebnis wird danach in ein Tab-Panel umgehängt, damit Tab-Leiste und
        // Inhalt gemeinsam sichtbar bleiben.
        await active[2]();
        const panel = element('div', {className: 'sub-tab-panel', children: [...workspace.children]});
        workspace.replaceChildren(tabStrip, panel);
    };

    // Modul „Einstellungen“: bündelt Bereiche, die entweder besondere Rechte (Benutzerverwaltung)
    // oder gleich die Admin-/Super-Admin-Rolle (PIN-Schutzverwaltung, E-Mail-Einstellungen)
    // voraussetzen — analog zu `showMembershipManagement` mit eigener Reiter-Leiste je nach
    // freigeschaltetem Zugang.
    const showSettingsManagement = async () => {
        const tabs = [
            ...(hasModule('user_management') ? [['users', 'Benutzerverwaltung', showUsers]] : []),
            // Bewusst über die Rolle statt über das reguläre Modul-Rechtesystem freigeschaltet:
            // PIN-Schutz und E-Mail-Einstellungen sind genau dafür da, auch vor Admins/Editoren mit
            // weitreichenden Modulrechten zusätzlich zu schützen bzw. sensible Zugangsdaten zu
            // verbergen — sie selbst über eine delegierbare Modulberechtigung zu steuern, würde
            // das aushebeln.
            ...(isGlobalAdministrator() ? [['pin', 'PIN-Schutzverwaltung', showPinProtection]] : []),
            ...(isGlobalAdministrator() ? [['email', 'E-Mail-Einstellungen', showEmailSettingsManagement]] : []),
        ];
        if (!tabs.some(([key]) => key === activeSettingsTab)) activeSettingsTab = tabs[0]?.[0] || null;
        if (activeSettingsTab && activeSettingsTab !== 'email') {
            setAdminPath(['einstellungen', settingsSlugsByTab[activeSettingsTab]], true);
        }

        const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'Einstellungen'}, children: tabs.map(([key, label]) => {
            const settingsPath = key === 'email'
                ? adminPath('einstellungen', 'e-mail', emailSlugsByTab[activeEmailSettingsTab || 'connection'])
                : adminPath('einstellungen', settingsSlugsByTab[key]);
            const button = element('a', {
                className: `sub-tab${key === activeSettingsTab ? ' active' : ''}`,
                text: label,
                attributes: {href: settingsPath},
            });
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                activeSettingsTab = key;
                if (key === 'email') {
                    activeEmailSettingsTab = activeEmailSettingsTab || 'connection';
                    setAdminPath(['einstellungen', 'e-mail', emailSlugsByTab[activeEmailSettingsTab]]);
                } else {
                    setAdminPath(['einstellungen', settingsSlugsByTab[key]]);
                }
                await showSettingsManagement();
            });

            return button;
        })});
        const active = tabs.find(([key]) => key === activeSettingsTab);
        if (!active) {
            workspace.replaceChildren(tabStrip, emptyState('Für diesen Zugang ist kein Bereich der Einstellungen freigeschaltet.'));
            return;
        }
        // showUsers/showPinProtection/showEmailSettingsManagement schreiben wie jedes andere Modul direkt in `workspace`. Deren
        // Ergebnis wird danach in ein Tab-Panel umgehängt, damit Tab-Leiste und Inhalt gemeinsam
        // sichtbar bleiben.
        await active[2]();
        const panel = element('div', {className: 'sub-tab-panel', children: [...workspace.children]});
        workspace.replaceChildren(tabStrip, panel);
    };

    const showUsers = async () => {
        const [data, pageOptions] = await Promise.all([
            request('/api/admin/v1/users'),
            request('/api/admin/v1/users/page-options'),
        ]);
        workspace.replaceChildren(
            sectionHeading('Benutzerverwaltung', 'Zugänge und Rollen verwalten'),
            ...(canEditModule('user_management') ? [userCreationForm(showUsers, pageOptions.items)] : []),
            element('div', {className: 'card-list', children: data.items.map((user) => userCard(user, showUsers, pageOptions.items))}),
        );
    };

    const openActivityDialog = (activity, onSaved) => {
        const dialog = element('dialog', {className: 'activity-dialog'});
        const name = field('Bezeichnung', `activity-name-${activity?.id || 'new'}`, activity?.name || '');
        const description = field('Beschreibung (optional)', `activity-description-${activity?.id || 'new'}`, activity?.description || '', 'textarea');
        const defaultRequiredHelpers = field(
            'Standard-Anzahl benötigter Helfer (optional)',
            `activity-default-required-helpers-${activity?.id || 'new'}`,
            activity?.defaultRequiredHelpers ?? '',
            'number',
        );
        const defaultRequiredHelpersInput = defaultRequiredHelpers.querySelector('input');
        defaultRequiredHelpersInput.min = '1';
        defaultRequiredHelpersInput.max = '999';
        const active = element('input', {attributes: {type: 'checkbox'}});
        active.checked = activity?.active !== false;
        const alwaysIncluded = element('input', {attributes: {type: 'checkbox'}});
        alwaysIncluded.checked = activity?.alwaysIncluded === true;
        const message = formMessage();
        const submit = element('button', {className: 'button', text: activity ? 'Änderungen speichern' : 'Aktivität anlegen', attributes: {type: 'submit'}});
        const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
        const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
        const form = element('form', {className: 'activity-dialog-content', children: [
            element('header', {children: [
                element('div', {children: [
                    element('p', {className: 'eyebrow', text: activity ? 'Aktivität bearbeiten' : 'Neue Aktivität'}),
                    element('h2', {text: activity?.name || 'Aktivität anlegen'}),
                ]}),
            ]}),
            name,
            description,
            defaultRequiredHelpers,
            element('small', {text: 'Wird beim Zuordnen der Aktivität zu einer Veranstaltung als Vorschlag für die benötigte Helferzahl übernommen.'}),
            element('label', {className: 'check-field', children: [active, element('span', {text: 'Aktivität ist auswählbar'})]}),
            element('label', {className: 'check-field', children: [alwaysIncluded, element('span', {text: 'Immer in neue Veranstaltungen einbinden'})]}),
            element('small', {text: 'Wird beim Anlegen einer neuen Veranstaltung automatisch zugeordnet.'}),
            message,
            element('div', {className: 'confirm-dialog-actions', children: [cancel, submit]}),
        ]});
        name.querySelector('input').required = true;
        cancel.addEventListener('click', () => dialog.close());
        close.addEventListener('click', () => dialog.close());
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            submit.disabled = true;
            try {
                await request(activity ? `/api/admin/v1/event-activities/${activity.id}` : '/api/admin/v1/event-activities', {
                    method: activity ? 'PUT' : 'POST',
                    body: JSON.stringify({
                        name: name.querySelector('input').value,
                        description: description.querySelector('textarea').value,
                        active: active.checked,
                        defaultRequiredHelpers: defaultRequiredHelpersInput.value === '' ? null : Number.parseInt(defaultRequiredHelpersInput.value, 10),
                        alwaysIncluded: alwaysIncluded.checked,
                    }),
                });
                toast(activity ? 'Aktivität wurde gespeichert.' : 'Aktivität wurde angelegt.');
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
    };

    const showActivities = async () => {
        const data = await request('/api/admin/v1/event-activities');
        const heading = sectionHeading('Aktivitäten', 'Wiederverwendbare Tätigkeiten für Veranstaltungen und gemeinsame Arbeitseinsätze');
        if (canEditModule('activities')) {
            const create = element('button', {className: 'button', text: '＋ Neue Aktivität', attributes: {type: 'button'}});
            create.addEventListener('click', () => openActivityDialog(null, showEventManagement));
            heading.append(create);
        }
        const rows = data.items.map((activity) => {
            const row = element('button', {
                className: `activity-list-row${activity.active ? '' : ' is-inactive'}`,
                attributes: {type: 'button', ...(canEditModule('activities') ? {} : {disabled: 'disabled'})},
                children: [
                    element('span', {className: 'activity-list-copy', children: [
                        element('strong', {text: activity.name}),
                        element('small', {text: activity.description || 'Keine Beschreibung hinterlegt.'}),
                        ...(activity.defaultRequiredHelpers ? [element('small', {text: `Standard: ${activity.defaultRequiredHelpers} Helfer`})] : []),
                    ]}),
                    ...(activity.alwaysIncluded ? [element('span', {className: 'status-badge', text: 'Immer eingebunden'})] : []),
                    element('span', {className: `status-badge ${activity.active ? 'status-active' : 'status-inactive'}`, text: activity.active ? 'Aktiv' : 'Inaktiv'}),
                    ...(canEditModule('activities') ? [element('span', {className: 'activity-list-edit', text: 'Bearbeiten ›'})] : []),
                ],
            });
            if (canEditModule('activities')) row.addEventListener('click', () => openActivityDialog(activity, showEventManagement));
            return row;
        });
        workspace.replaceChildren(
            heading,
            element('div', {className: 'activity-list', children: data.items.length
                ? rows
                : [emptyState('Noch keine Aktivitäten angelegt.')]}),
        );
    };

    const openEventDialog = (schedule, kind, onSaved, handlers) => {
        const draft = {
            content: schedule?.content || '',
            mediaUrl: schedule?.mediaUrl || null,
            mediaAlt: schedule?.mediaAlt || null,
            mediaSource: schedule?.mediaSource || null,
        };
        const effectiveKind = schedule?.kind || kind;
        const dialogKey = schedule?.id || 'new';
        const dialog = element('dialog', {className: 'event-schedule-dialog'});

        const title = field('Überschrift', 'event-title-' + dialogKey, schedule?.title || '');
        const date = field('Datum', 'event-date-' + dialogKey, schedule?.date || '', 'date');
        const time = field('Uhrzeit', 'event-time-' + dialogKey, schedule?.time || '14:00', 'time');
        title.querySelector('input').required = true;
        date.querySelector('input').required = true;
        time.querySelector('input').required = true;

        const visible = element('input', {attributes: {type: 'checkbox'}});
        visible.checked = schedule ? schedule.visible !== false : true;

        const helpEnabled = element('input', {attributes: {type: 'checkbox'}});
        helpEnabled.checked = schedule ? schedule.helpEnabled === true : effectiveKind === 'work_assignment';
        const helpLabel = field('Beschriftung des Buttons', 'event-help-label-' + dialogKey, schedule?.helpButtonLabel || 'Ich möchte helfen!');
        const helpLabelInput = helpLabel.querySelector('input');

        let activityCatalog = (handlers.activities || []).slice();
        // Bei einer neuen Veranstaltung werden alle als „immer einbinden“ markierten Aktivitäten
        // automatisch vorbelegt; beim Bearbeiten bleiben die gespeicherten Zuordnungen unangetastet.
        const activities = schedule
            ? schedule.activities.map((activity) => ({...activity}))
            : activityCatalog
                .filter((activity) => activity.active && activity.alwaysIncluded)
                .map((activity) => ({
                    activityId: activity.id,
                    requiredHelpers: String(activity.defaultRequiredHelpers ?? 1),
                    time: null, meetTime: null, meetPlace: null, remark: null,
                }));
        const activityList = element('div', {className: 'event-activity-editor-list'});
        const renderActivityRows = () => {
            activityList.replaceChildren(...activities.map((assignment, assignmentIndex) => {
                const fieldId = (name) => `event-activity-${name}-${dialogKey}-${assignmentIndex}`;
                const activityField = (label, name, control, modifier = '') => {
                    const id = fieldId(name);
                    control.id = id;
                    return element('label', {
                        className: `event-activity-field event-activity-field-${name}${modifier ? ` ${modifier}` : ''}`,
                        attributes: {for: id},
                        children: [element('span', {text: label}), control],
                    });
                };
                const select = element('select');
                activityCatalog.forEach((activity) => {
                    if (!activity.active && activity.id !== assignment.activityId) return;
                    if (activity.id !== assignment.activityId && activities.some((item) => item.activityId === activity.id)) return;
                    select.append(element('option', {text: `${activity.name}${activity.active ? '' : ' (inaktiv)'}`, attributes: {value: activity.id}}));
                });
                select.value = assignment.activityId;
                select.addEventListener('change', () => {
                    assignment.activityId = select.value;
                    const selectedActivity = activityCatalog.find((activity) => activity.id === select.value);
                    if (selectedActivity?.defaultRequiredHelpers) {
                        assignment.requiredHelpers = String(selectedActivity.defaultRequiredHelpers);
                        count.value = String(selectedActivity.defaultRequiredHelpers);
                    }
                });
                const count = element('input', {className: 'event-activity-detail-input', attributes: {
                    type: 'number', inputmode: 'numeric', value: String(assignment.requiredHelpers ?? 1),
                    'aria-label': 'Anzahl benötigter Helfer',
                }});
                count.addEventListener('input', () => {
                    assignment.requiredHelpers = count.value;
                    if (count.hasAttribute('aria-invalid')) message.textContent = '';
                    count.setCustomValidity('');
                    count.removeAttribute('aria-invalid');
                });
                const timeInput = element('input', {className: 'event-activity-detail-input', attributes: {type: 'time', 'aria-label': 'Start'}});
                timeInput.value = assignment.time || '';
                timeInput.addEventListener('input', () => assignment.time = timeInput.value || null);
                const meetTimeInput = element('input', {className: 'event-activity-detail-input', attributes: {type: 'time', 'aria-label': 'Ende'}});
                meetTimeInput.value = assignment.meetTime || '';
                meetTimeInput.addEventListener('input', () => assignment.meetTime = meetTimeInput.value || null);
                const meetPlaceInput = element('input', {className: 'event-activity-detail-input', attributes: {type: 'text', maxlength: '160', 'aria-label': 'Treffort (optional)'}});
                meetPlaceInput.value = assignment.meetPlace || '';
                meetPlaceInput.addEventListener('input', () => assignment.meetPlace = meetPlaceInput.value || null);
                const remarkInput = element('input', {className: 'event-activity-detail-input', attributes: {type: 'text', maxlength: '500', 'aria-label': 'Bemerkung (optional)'}});
                remarkInput.value = assignment.remark || '';
                remarkInput.addEventListener('input', () => assignment.remark = remarkInput.value || null);
                assignment.remarkExpanded ??= false;
                const remarkToggleLabel = assignment.remarkExpanded
                    ? 'Bemerkungsfeld ausblenden'
                    : (assignment.remark ? 'Bemerkung bearbeiten' : 'Bemerkung hinzufügen');
                const remarkToggle = element('button', {
                    className: `secondary-button event-activity-remark-toggle${assignment.remark ? ' has-value' : ''}`,
                    attributes: {
                        type: 'button',
                        title: remarkToggleLabel,
                        'aria-label': remarkToggleLabel,
                        'aria-expanded': String(assignment.remarkExpanded),
                        ...(assignment.remarkExpanded ? {'aria-controls': fieldId('remark')} : {}),
                    },
                    children: [
                        element('span', {className: 'event-activity-remark-toggle-icon', text: assignment.remarkExpanded ? '−' : '＋', attributes: {'aria-hidden': 'true'}}),
                        element('span', {className: 'event-activity-remark-toggle-label', text: 'Bemerkung'}),
                    ],
                });
                remarkToggle.addEventListener('click', () => {
                    assignment.remarkExpanded = !assignment.remarkExpanded;
                    renderActivityRows();
                    if (assignment.remarkExpanded) activityList.querySelector(`#${CSS.escape(fieldId('remark'))}`)?.focus();
                });
                const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', title: 'Zuordnung entfernen', 'aria-label': 'Zuordnung entfernen'}});
                remove.addEventListener('click', () => {
                    activities.splice(assignmentIndex, 1);
                    renderActivityRows();
                });

                return element('div', {className: 'event-schedule-activity-row', children: [
                    activityField('Aktivität', 'activity', select),
                    activityField('Anzahl', 'count', count),
                    activityField('Start', 'start', timeInput),
                    activityField('Ende', 'end', meetTimeInput),
                    activityField('Treffort (optional)', 'meet-place', meetPlaceInput),
                    remarkToggle,
                    remove,
                    ...(assignment.remarkExpanded ? [activityField('Bemerkung (optional)', 'remark', remarkInput)] : []),
                ]});
            }));
        };
        const addActivity = element('button', {className: 'secondary-button', text: '＋ Aktivität zuordnen', attributes: {type: 'button'}});
        addActivity.addEventListener('click', () => {
            const available = activityCatalog.find((activity) => activity.active && !activities.some((item) => item.activityId === activity.id));
            if (!available) {
                toast('Keine weitere aktive Aktivität verfügbar.', 'error');
                return;
            }
            activities.push({activityId: available.id, requiredHelpers: String(available.defaultRequiredHelpers ?? 1), time: null, meetTime: null, meetPlace: null, remark: null});
            renderActivityRows();
        });
        const addNewActivity = element('button', {className: 'secondary-button', text: '＋ Neue Aktivität anlegen', attributes: {type: 'button'}});
        addNewActivity.addEventListener('click', () => {
            const knownIds = new Set(activityCatalog.map((activity) => activity.id));
            openActivityDialog(null, async () => {
                const refreshed = await request('/api/admin/v1/event-activities');
                activityCatalog = refreshed.items;
                handlers.activities = refreshed.items;
                const createdActivity = activityCatalog.find((activity) => !knownIds.has(activity.id));
                if (createdActivity) {
                    activities.push({activityId: createdActivity.id, requiredHelpers: String(createdActivity.defaultRequiredHelpers ?? 1), time: null, meetTime: null, meetPlace: null, remark: null});
                }
                toast('Aktivität wurde angelegt und zugeordnet.');
                renderActivityRows();
            });
        });
        renderActivityRows();
        const activityEditor = element('fieldset', {className: 'event-activity-editor', children: [
            element('legend', {text: 'Aktivitäten für die Helferanmeldung'}),
            element('small', {text: 'Start, Ende, Treffort und Bemerkung werden Helfern beim Anmelden angezeigt.'}),
            activityList,
            element('div', {className: 'event-activity-editor-actions', children: [addActivity, addNewActivity]}),
        ]});
        const helpConfiguration = element('div', {className: 'event-help-configuration', children: [helpLabel, activityEditor]});
        helpConfiguration.hidden = !helpEnabled.checked;
        helpEnabled.addEventListener('change', () => helpConfiguration.hidden = !helpEnabled.checked);

        const callToActions = (schedule?.callToActions || []).map((action) => ({...action}));
        const actionList = element('div', {className: 'event-call-action-editor-list'});
        const renderActions = () => {
            actionList.replaceChildren(...callToActions.map((action, actionIndex) => {
                const label = field('Button-Beschriftung', `event-action-label-${dialogKey}-${actionIndex}`, action.label || 'Mehr erfahren');
                const labelInput = label.querySelector('input');
                labelInput.maxLength = 80;
                labelInput.required = true;
                labelInput.addEventListener('input', () => action.label = labelInput.value);

                const targetType = element('select', {attributes: {'aria-label': 'Art des Linkziels'}, children: [
                    element('option', {text: 'URL verlinken', attributes: {value: 'url'}}),
                    element('option', {text: 'CMS-Seite verlinken', attributes: {value: 'page'}}),
                ]});
                targetType.value = action.pageId ? 'page' : 'url';
                const targetField = element('div', {className: 'event-call-action-target'});
                const renderTarget = () => {
                    if (targetType.value === 'page') {
                        const pageSelect = element('select', {attributes: {'aria-label': 'Verlinkte CMS-Seite'}});
                        pageSelect.append(element('option', {text: 'Seite auswählen …', attributes: {value: ''}}));
                        flattenPageTree(buildPageTree(handlers.pages || [])).forEach(({page: candidate, depth}) => {
                            pageSelect.append(element('option', {
                                text: `${'— '.repeat(depth)}${candidate.title}${candidate.visible ? '' : ' (ausgeblendet)'}`,
                                attributes: {value: candidate.id},
                            }));
                        });
                        pageSelect.value = action.pageId || '';
                        pageSelect.required = true;
                        pageSelect.addEventListener('change', () => action.pageId = pageSelect.value || null);
                        targetField.replaceChildren(element('label', {className: 'field', children: [element('span', {text: 'Verlinkte Seite'}), pageSelect]}));
                        return;
                    }
                    const url = field('URL', `event-action-url-${dialogKey}-${actionIndex}`, action.url || '/');
                    const urlInput = url.querySelector('input');
                    urlInput.maxLength = 2048;
                    urlInput.required = true;
                    urlInput.addEventListener('input', () => action.url = urlInput.value);
                    targetField.replaceChildren(url);
                };
                targetType.addEventListener('change', () => {
                    if (targetType.value === 'page') {
                        action.url = null;
                    } else {
                        action.pageId = null;
                        action.url = '/';
                    }
                    renderTarget();
                });
                renderTarget();
                const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', title: 'Aktionsbutton entfernen', 'aria-label': 'Aktionsbutton entfernen'}});
                remove.addEventListener('click', () => {
                    callToActions.splice(actionIndex, 1);
                    renderActions();
                });

                return element('div', {className: 'event-call-action-editor-row', children: [
                    label,
                    element('label', {className: 'field', children: [element('span', {text: 'Linkziel'}), targetType]}),
                    targetField,
                    remove,
                ]});
            }));
        };
        const addAction = element('button', {className: 'secondary-button', text: '＋ Aktionsbutton hinzufügen', attributes: {type: 'button'}});
        addAction.addEventListener('click', () => {
            callToActions.push({label: 'Mehr erfahren', url: '/', pageId: null});
            renderActions();
        });
        renderActions();

        const message = formMessage();
        const submitLabel = schedule
            ? 'Änderungen speichern'
            : (effectiveKind === 'work_assignment' ? 'Arbeitseinsatz anlegen' : 'Veranstaltung anlegen');
        const submit = element('button', {
            className: 'button event-dialog-action event-dialog-action-save',
            attributes: {type: 'submit', title: submitLabel, 'aria-label': submitLabel},
            children: [
                element('span', {className: 'event-dialog-action-icon', text: '✓', attributes: {'aria-hidden': 'true'}}),
                element('span', {className: 'event-dialog-action-label', text: submitLabel}),
            ],
        });
        const cancel = element('button', {
            className: 'secondary-button event-dialog-action event-dialog-action-cancel',
            attributes: {type: 'button', title: 'Abbrechen', 'aria-label': 'Abbrechen'},
            children: [
                element('span', {className: 'event-dialog-action-icon', text: '×', attributes: {'aria-hidden': 'true'}}),
                element('span', {className: 'event-dialog-action-label', text: 'Abbrechen'}),
            ],
        });
        const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
        const actions = [cancel, submit];
        if (schedule) {
            const deleteButton = element('button', {
                className: 'text-button danger event-dialog-action event-dialog-action-delete',
                attributes: {type: 'button', title: 'Löschen', 'aria-label': 'Löschen'},
                children: [
                    element('span', {className: 'event-dialog-action-icon', text: '⌫', attributes: {'aria-hidden': 'true'}}),
                    element('span', {className: 'event-dialog-action-label', text: 'Löschen'}),
                ],
            });
            deleteButton.addEventListener('click', async () => {
                const confirmed = await confirmAction(
                    `„${schedule.title}“ löschen?`,
                    'Der Eintrag wird endgültig entfernt. Bereits eingegangene Helferanmeldungen bleiben im Modul „Veranstaltungshelfer“ einsehbar.',
                    'Löschen',
                );
                if (!confirmed) return;
                try {
                    await request('/api/admin/v1/events/' + schedule.id, {method: 'DELETE'});
                    toast('Der Eintrag wurde gelöscht.');
                    dialog.close();
                    await onSaved();
                } catch (error) {
                    toast(error.message, 'error');
                }
            });
            actions.unshift(deleteButton);
        }

        const form = element('form', {className: 'event-schedule-dialog-content', children: [
            element('header', {children: [
                element('div', {children: [
                    element('p', {className: 'eyebrow', text: EVENT_SCHEDULE_KIND_LABELS[effectiveKind] || effectiveKind}),
                    element('h2', {text: schedule ? schedule.title : (effectiveKind === 'work_assignment' ? 'Arbeitseinsatz anlegen' : 'Veranstaltung anlegen')}),
                ]}),
            ]}),
            title,
            element('div', {className: 'form-grid', children: [date, time]}),
            element('div', {className: 'field', children: [
                element('span', {text: 'Zusatzinformationen (optional)'}),
                richTextEditor(draft, 'event-' + dialogKey, null, 'Zusatzinformationen zur Veranstaltung'),
            ]}),
            collectionItemMediaEditor(draft, 'event-' + dialogKey),
            element('label', {className: 'check-field', children: [visible, element('span', {text: 'Im Frontend sichtbar'})]}),
            element('label', {className: 'check-field event-help-option', children: [
                helpEnabled,
                element('span', {text: 'Im Frontend den Button „Ich möchte helfen!“ mit Anmeldeformular anzeigen'}),
            ]}),
            helpConfiguration,
            element('fieldset', {className: 'event-call-action-editor', children: [
                element('legend', {text: 'Weitere Aktionsbuttons'}),
                element('small', {text: 'Optional können weitere Buttons auf eine URL oder eine CMS-Seite verweisen.'}),
                actionList,
                addAction,
            ]}),
            message,
            element('div', {className: 'confirm-dialog-actions', children: actions}),
        ]});

        cancel.addEventListener('click', () => dialog.close());
        close.addEventListener('click', () => dialog.close());
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            submit.disabled = true;
            try {
                const countInputs = activityList.querySelectorAll('.event-activity-field-count input');
                const invalidCount = Array.from(countInputs).find((input, index) => {
                    const value = Number(input.value);
                    const valid = input.value.trim() !== '' && Number.isInteger(value) && value > 0 && value <= 999;
                    input.setCustomValidity(valid ? '' : 'Bitte eine ganze Zahl zwischen 1 und 999 eingeben.');
                    input.toggleAttribute('aria-invalid', !valid);
                    if (valid) activities[index].requiredHelpers = value;
                    return !valid;
                });
                if (invalidCount) {
                    message.textContent = 'Die Anzahl der benötigten Helfer muss zwischen 1 und 999 liegen.';
                    invalidCount.reportValidity();
                    invalidCount.focus();
                    submit.disabled = false;
                    return;
                }
                const payload = {
                    title: title.querySelector('input').value,
                    date: date.querySelector('input').value,
                    time: time.querySelector('input').value,
                    content: draft.content,
                    mediaUrl: draft.mediaUrl,
                    mediaAlt: draft.mediaAlt,
                    mediaSource: draft.mediaSource,
                    helpEnabled: helpEnabled.checked,
                    helpButtonLabel: helpLabelInput.value || null,
                    visible: visible.checked,
                    activities: activities.map((activity) => ({
                        activityId: activity.activityId,
                        requiredHelpers: activity.requiredHelpers,
                        time: activity.time || null,
                        meetTime: activity.meetTime || null,
                        meetPlace: activity.meetPlace || null,
                        remark: activity.remark || null,
                    })),
                    callToActions,
                };
                if (schedule) {
                    await request('/api/admin/v1/events/' + schedule.id, {method: 'PUT', body: JSON.stringify(payload)});
                    toast('Änderungen wurden gespeichert.');
                } else {
                    await request('/api/admin/v1/events', {method: 'POST', body: JSON.stringify({...payload, kind: effectiveKind})});
                    toast(effectiveKind === 'work_assignment' ? 'Arbeitseinsatz wurde angelegt.' : 'Veranstaltung wurde angelegt.');
                }
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
    };

    let eventScheduleKindFilter = '';
    const showEvents = async () => {
        const [scheduleData, activityData] = await Promise.all([
            request('/api/admin/v1/events'),
            request('/api/admin/v1/event-activities'),
        ]);
        let pages = [];
        try {
            pages = (await request('/api/admin/v1/pages')).items;
        } catch {
            pages = [];
        }
        const handlers = {activities: activityData.items, pages};

        const filterSelect = element('select', {attributes: {'aria-label': 'Nach Art filtern'}, children: [
            element('option', {text: 'Alle', attributes: {value: ''}}),
            element('option', {text: 'Nur Veranstaltungen', attributes: {value: 'event'}}),
            element('option', {text: 'Nur Arbeitseinsätze', attributes: {value: 'work_assignment'}}),
        ]});
        filterSelect.value = eventScheduleKindFilter;
        filterSelect.addEventListener('change', async () => {
            eventScheduleKindFilter = filterSelect.value;
            await showEventManagement();
        });

        const items = scheduleData.items.filter((item) => !eventScheduleKindFilter || item.kind === eventScheduleKindFilter);

        const renderRow = (item) => {
            const row = element('button', {
                className: `activity-list-row event-kind-${item.kind}${item.visible ? '' : ' is-inactive'}`,
                attributes: {type: 'button', ...(canEditModule('events') ? {} : {disabled: 'disabled'})},
                children: [
                    element('span', {className: 'activity-list-copy', children: [
                        element('strong', {text: item.title}),
                        element('small', {text: `${new Date(`${item.date}T00:00:00`).toLocaleDateString('de-DE')} · ${item.time} Uhr`}),
                    ]}),
                    element('span', {className: `status-badge event-kind-badge-${item.kind}`, text: EVENT_SCHEDULE_KIND_LABELS[item.kind] || item.kind}),
                    ...(item.visible ? [] : [element('span', {className: 'status-badge status-inactive', text: 'Ausgeblendet'})]),
                    ...(canEditModule('events') ? [element('span', {className: 'activity-list-edit', text: 'Bearbeiten ›'})] : []),
                ],
            });
            if (canEditModule('events')) row.addEventListener('click', () => openEventDialog(item, item.kind, showEventManagement, handlers));
            return row;
        };

        const {currentYear, todayItems, upcomingItems, completedCurrentYearItems, archiveByYear} =
            bucketItemsByDate(items, (item) => item.date, (item) => item.time);
        const section = (title, sectionItems, modifier = '') => element('section', {
            className: `event-helper-section ${modifier}`.trim(),
            children: [
                element('div', {className: 'event-helper-section-heading', children: [
                    element('h3', {text: title}),
                    element('span', {className: 'status-badge', text: String(sectionItems.length)}),
                ]}),
                element('div', {className: 'activity-list', children: sectionItems.map(renderRow)}),
            ],
        });
        const archive = (title, sectionItems) => element('details', {className: 'event-helper-archive', children: [
            element('summary', {children: [
                element('strong', {text: title}),
                element('span', {className: 'status-badge', text: String(sectionItems.length)}),
            ]}),
            element('div', {className: 'event-helper-archive-list', children: [element('div', {className: 'activity-list', children: sectionItems.map(renderRow)})]}),
        ]});
        const sections = [
            ...(todayItems.length ? [section('Heute', todayItems, 'event-helper-section-today')] : []),
            ...(upcomingItems.length ? [section('Kommende', upcomingItems)] : []),
            ...(completedCurrentYearItems.length ? [archive(`Abgeschlossen ${currentYear}`, completedCurrentYearItems)] : []),
            ...[...archiveByYear.entries()].sort(([firstYear], [secondYear]) => secondYear - firstYear)
                .map(([year, yearItems]) => archive(`Archiv ${year}`, yearItems)),
        ];

        const heading = sectionHeading('Veranstaltungen', 'Veranstaltungen und Arbeitseinsätze verwalten');
        if (canEditModule('events')) {
            const createEvent = element('button', {className: 'button event-create-button event-create-event', text: '＋ Veranstaltung erstellen', attributes: {type: 'button'}});
            const createWorkAssignment = element('button', {className: 'button event-create-button event-create-work-assignment', text: '＋ Arbeitseinsatz erstellen', attributes: {type: 'button'}});
            createEvent.addEventListener('click', () => openEventDialog(null, 'event', showEventManagement, handlers));
            createWorkAssignment.addEventListener('click', () => openEventDialog(null, 'work_assignment', showEventManagement, handlers));
            heading.append(createEvent, createWorkAssignment);
        }

        workspace.replaceChildren(
            heading,
            element('div', {className: 'management-toolbar', children: [filterSelect, element('span', {text: `${items.length} Einträge`})]}),
            element('div', {className: 'event-helper-groups', children: sections.length ? sections : [emptyState('Noch keine Veranstaltungen oder Arbeitseinsätze angelegt.')]}),
        );
    };

    const showEventManagement = async () => {
        const tabs = [
            ...(hasModule('events') ? [['events', 'Veranstaltungen', showEvents]] : []),
            ...(hasModule('event_helpers') ? [['helpers', 'Veranstaltungshelfer', showEventHelpers]] : []),
            ...(hasModule('activities') ? [['activities', 'Aktivitäten', showActivities]] : []),
        ];
        if (!tabs.some(([key]) => key === activeEventTab)) activeEventTab = tabs[0]?.[0] || null;
        if (activeEventTab) {
            setAdminPath(['veranstaltungen', eventSlugsByTab[activeEventTab]], true);
        }

        const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'Veranstaltung'}, children: tabs.map(([key, label]) => {
            const button = element('a', {
                className: `sub-tab${key === activeEventTab ? ' active' : ''}`,
                text: label,
                attributes: {href: adminPath('veranstaltungen', eventSlugsByTab[key])},
            });
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                activeEventTab = key;
                setAdminPath(['veranstaltungen', eventSlugsByTab[key]]);
                await showEventManagement();
            });

            return button;
        })});
        const active = tabs.find(([key]) => key === activeEventTab);
        if (!active) {
            workspace.replaceChildren(tabStrip, emptyState('Für diesen Zugang ist kein Bereich der Veranstaltungsverwaltung freigeschaltet.'));
            return;
        }
        // showEvents/showEventHelpers/showActivities schreiben wie jedes andere Modul direkt in
        // `workspace`. Deren Ergebnis wird danach in ein Tab-Panel umgehängt, damit Tab-Leiste und
        // Inhalt gemeinsam sichtbar bleiben.
        await active[2]();
        const panel = element('div', {className: 'sub-tab-panel', children: [...workspace.children]});
        workspace.replaceChildren(tabStrip, panel);
    };

    const showContactFeedbackManagement = async () => {
        const tabs = [
            ...(hasModule('contact_requests') ? [['contact', 'Kontaktanfrage', showContact]] : []),
            ...(hasModule('guestbook') ? [['guestbook', 'Gästebuch', showGuestbook]] : []),
        ];
        if (!tabs.some(([key]) => key === activeContactTab)) activeContactTab = tabs[0]?.[0] || null;
        if (activeContactTab) {
            setAdminPath(['kontakt-feedback', contactSlugsByTab[activeContactTab]], true);
        }

        const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'Kontakt und Feedback'}, children: tabs.map(([key, label]) => {
            const button = element('a', {
                className: `sub-tab${key === activeContactTab ? ' active' : ''}`,
                text: label,
                attributes: {href: adminPath('kontakt-feedback', contactSlugsByTab[key])},
            });
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                activeContactTab = key;
                setAdminPath(['kontakt-feedback', contactSlugsByTab[key]]);
                await showContactFeedbackManagement();
            });

            return button;
        })});
        const active = tabs.find(([key]) => key === activeContactTab);
        if (!active) {
            workspace.replaceChildren(tabStrip, emptyState('Für diesen Zugang ist kein Bereich von Kontakt und Feedback freigeschaltet.'));
            return;
        }
        await active[2]();
        const panel = element('div', {className: 'sub-tab-panel', children: [...workspace.children]});
        workspace.replaceChildren(tabStrip, panel);
    };

    const applyAdminSegments = (segments) => {
        activeMembershipTab = segments[0] === 'mitglieder' ? membershipTabsBySlug[segments[1]] ?? null : activeMembershipTab;
        activeEventTab = segments[0] === 'veranstaltungen' ? eventTabsBySlug[segments[1]] ?? null : activeEventTab;
        activeContactTab = segments[0] === 'kontakt-feedback' ? contactTabsBySlug[segments[1]] ?? null : activeContactTab;
        activeSettingsTab = segments[0] === 'einstellungen' ? settingsTabsBySlug[segments[1]] ?? null : activeSettingsTab;
        if (segments[0] === 'einstellungen' && segments[1] === 'e-mail') {
            activeEmailSettingsTab = emailTabsBySlug[segments[2]] ?? null;
        }
    };
    let menuItems = [];
    const activateMenu = async (item, segments, updateHistory = false) => {
        applyAdminSegments(segments);
        if (updateHistory) setAdminPath(segments);
        menu.querySelectorAll('.admin-menu-item').forEach((menuItem) => menuItem.classList.remove('active'));
        item.link.classList.add('active');
        item.link.setAttribute('aria-current', 'page');
        menu.querySelectorAll('.admin-menu-item:not(.active)').forEach((menuItem) => menuItem.removeAttribute('aria-current'));
        closeAdminNavigation();
        try {
            await item.action();
        } catch (error) {
            toast(error.message, 'error');
            workspace.replaceChildren(emptyState(error.message));
        }
    };
    const addMenu = (label, slug, defaultSegments, action) => {
        const link = element('a', {
            className: 'admin-menu-item',
            text: label,
            attributes: {href: adminPath(...defaultSegments)},
        });
        const item = {slug, defaultSegments, action, link};
        link.addEventListener('click', async (event) => {
            event.preventDefault();
            await activateMenu(item, defaultSegments, true);
        });
        menu.append(link);

        return item;
    };
    menuItems = [];
    if (hasModule('pages')) menuItems.push(addMenu('Seiten', 'seiten', ['seiten'], showPages));

    if (hasModule('events') || hasModule('event_helpers') || hasModule('activities')) {
        const defaultEventSlug = hasModule('events') ? 'termine' : hasModule('event_helpers') ? 'helfer' : 'aktivitaeten';
        menuItems.push(addMenu('Veranstaltung', 'veranstaltungen', ['veranstaltungen', defaultEventSlug], showEventManagement));
    }

    if (hasModule('members') || hasModule('contribution_rates') || hasModule('membership_applications') || hasModule('member_messages')) {
        const defaultMembershipSlug = hasModule('members')
            ? 'dashboard'
            : hasModule('contribution_rates')
                ? 'beitragssaetze'
                : hasModule('membership_applications') ? 'antraege' : 'nachrichten';
        menuItems.push(addMenu('Mitgliederverwaltung', 'mitglieder', ['mitglieder', defaultMembershipSlug], async () => {
            if (!(await ensurePinUnlocked('members.module_access', 'Mitgliederverwaltung'))) return;
            await showMembershipManagement();
        }));
    }

    if (hasModule('contact_requests') || hasModule('guestbook')) {
        const defaultContactSlug = hasModule('contact_requests') ? 'kontaktanfragen' : 'gaestebuch';
        menuItems.push(addMenu('Kontakt und Feedback', 'kontakt-feedback', ['kontakt-feedback', defaultContactSlug], showContactFeedbackManagement));
    }

    if (hasModule('user_management') || isGlobalAdministrator()) {
        const defaultSettingsSlug = hasModule('user_management') ? 'benutzer' : 'pin-schutz';
        menuItems.push(addMenu('Einstellungen', 'einstellungen', ['einstellungen', defaultSettingsSlug], showSettingsManagement));
    }

    const openAdminRoute = async (segments, replaceInvalid = false) => {
        const item = menuItems.find((menuItem) => menuItem.slug === segments[0]) || menuItems[0];
        if (!item) {
            workspace.replaceChildren(emptyState('Für diesen Zugang ist kein Redaktionsmodul freigeschaltet.'));
            return;
        }
        const validNestedRoute = item.slug === 'mitglieder'
            ? segments.length === 2 && membershipTabsBySlug[segments[1]]
            : item.slug === 'veranstaltungen'
                ? segments.length === 2 && eventTabsBySlug[segments[1]]
                : item.slug === 'kontakt-feedback'
                    ? segments.length === 2 && contactTabsBySlug[segments[1]]
                    : item.slug === 'einstellungen'
                        ? (segments.length === 2 && ['benutzer', 'pin-schutz'].includes(segments[1]))
                            || (segments.length === 3 && segments[1] === 'e-mail' && emailTabsBySlug[segments[2]])
                        : segments.length === 1;
        const requestedSegments = item.slug === segments[0] && validNestedRoute ? segments : item.defaultSegments;
        if (replaceInvalid || requestedSegments !== segments) setAdminPath(requestedSegments, true);
        await activateMenu(item, requestedSegments);
    };

    const logout = element('button', {className: 'text-button', text: 'Abmelden', attributes: {type: 'button'}});
    logout.addEventListener('click', async () => {
        await request('/api/auth/v1/logout', {method: 'POST'});
        csrfToken = null;
        currentRoles = [];
        currentModuleAccess = {};
        currentPageAccess = null;
        clearPinSessionUnlocks();
        window.onpopstate = null;
        toast('Du wurdest abgemeldet.', 'info');
        renderLogin();
    });

    app.replaceChildren(
        element('header', {className: 'admin-header', children: [
            element('a', {className: 'admin-brand', text: 'Waldbad · Redaktion', attributes: {href: '/admin'}}),
            navigationToggle,
            element('div', {className: 'admin-account', children: [
                element('span', {text: session.user.displayName}),
                logout,
            ]}),
        ]}),
        adminLayout,
    );
    app.onkeydown = (event) => {
        if (event.key === 'Escape' && adminLayout.classList.contains('admin-nav-open')) {
            closeAdminNavigation();
            navigationToggle.focus();
        }
    };
    window.onpopstate = () => openAdminRoute(currentAdminSegments());
    await openAdminRoute(initialAdminSegments, initialAdminSegments.length === 0);
};

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

const guestbookStatusLabel = (status) => ({
    pending: 'Neu',
    published: 'Veröffentlicht',
    rejected: 'Abgelehnt',
    spam: 'Spam',
}[status] || status);

const moderationCard = (entry, refresh) => element('article', {className: `management-card guestbook-card status-${entry.status}`, children: [
    element('header', {children: [element('strong', {text: entry.displayName}), element('small', {text: guestbookStatusLabel(entry.status) + ' · ' + new Date(entry.submittedAt).toLocaleString('de-DE')})]}),
    element('p', {text: entry.message}),
    ...(entry.email ? [element('a', {text: entry.email, attributes: {href: 'mailto:' + entry.email}})] : []),
    ...(canEditModule('guestbook') ? [element('div', {className: 'card-actions', children: [
        ...(entry.status !== 'published' ? [actionButton('Freigeben', `/api/admin/v1/guestbook-entries/${entry.id}/approve`, refresh, 'button', {success: 'Gästebucheintrag wurde freigegeben.'})] : []),
        ...(entry.status !== 'rejected' ? [actionButton('Ablehnen', `/api/admin/v1/guestbook-entries/${entry.id}/reject`, refresh, 'secondary-button', {
            success: 'Gästebucheintrag wurde abgelehnt.',
            confirm: {title: 'Eintrag ablehnen?', description: 'Der Eintrag wird nicht im öffentlichen Gästebuch angezeigt.', label: 'Ablehnen'},
        })] : []),
        ...(entry.status !== 'spam' ? [actionButton('Spam', `/api/admin/v1/guestbook-entries/${entry.id}/mark-spam`, refresh, 'text-button danger', {
            success: 'Gästebucheintrag wurde als Spam markiert.',
            confirm: {title: 'Als Spam markieren?', description: 'Der Eintrag wird als Spam eingestuft und nicht veröffentlicht.', label: 'Als Spam markieren'},
        })] : []),
    ]})] : []),
]});

const contactCard = (item, refresh) => element('article', {className: 'management-card', children: [
    element('header', {children: [element('strong', {text: item.subject || 'Kontaktanfrage'}), element('small', {text: item.status + ' · ' + new Date(item.submittedAt).toLocaleString('de-DE')})]}),
    element('p', {text: item.message}),
    element('a', {text: item.name + ' · ' + item.email, attributes: {href: 'mailto:' + item.email}}),
    ...(canEditModule('contact_requests') ? [element('div', {className: 'card-actions', children: [
        actionButton('In Bearbeitung', `/api/admin/v1/contact-requests/${item.id}/status/in_progress`, refresh, 'secondary-button', {success: 'Kontaktanfrage ist jetzt in Bearbeitung.'}),
        actionButton('Erledigt', `/api/admin/v1/contact-requests/${item.id}/status/resolved`, refresh, 'button', {success: 'Kontaktanfrage wurde als erledigt markiert.'}),
    ]})] : []),
]});

// Nachrichten, die Mitglieder über „Meine Mitgliedschaft" gesendet haben (siehe
// `SendMemberMessageUseCase`) — an einen konkreten Mitgliedsdatensatz gebunden, daher der direkte
// Link ins Mitgliederverwaltung-Modul statt einer mailto-Adresse wie bei Kontaktanfragen.
const memberMessageCard = (item, refresh) => element('article', {className: 'management-card', children: [
    element('header', {children: [
        element('strong', {text: `${item.memberName} (${item.memberNumber})`}),
        element('small', {text: item.status + ' · ' + new Date(item.submittedAt).toLocaleString('de-DE')}),
    ]}),
    element('p', {text: item.message}),
    ...(canEditModule('member_messages') ? [element('div', {className: 'card-actions', children: [
        actionButton('In Bearbeitung', `/api/admin/v1/member-messages/${item.id}/status/in_progress`, refresh, 'secondary-button', {success: 'Nachricht ist jetzt in Bearbeitung.'}),
        actionButton('Erledigt', `/api/admin/v1/member-messages/${item.id}/status/resolved`, refresh, 'button', {success: 'Nachricht wurde als erledigt markiert.'}),
    ]})] : []),
]});

const MODULE_ROLE_LABELS = {
    viewer: 'Viewer',
    editor: 'Editor',
    publisher: 'Publisher',
    moderator: 'Moderator',
};

const globalRoleOptions = () => [
    ['', 'Keine globale Administratorrolle'],
    ...(hasAnyRole('admin', 'super_admin') ? [['admin', 'Admin']] : []),
    ...(hasAnyRole('super_admin') ? [['super_admin', 'Super-Admin']] : []),
];

const globalRoleField = (selectedRole = '') => {
    const select = element('select', {
        attributes: {name: 'globalRole'},
        children: globalRoleOptions().map(([value, label]) => element('option', {text: label, attributes: {value}})),
    });
    select.value = selectedRole;

    return element('label', {children: [element('span', {text: 'Globale Rolle'}), select]});
};

const moduleAccessFields = (selectedAccess = {}) => element('div', {
    className: 'module-access-list',
    children: CMS_MODULES.map(([module, label]) => {
        const roles = module === 'pages'
            ? ['viewer', 'editor', 'publisher', 'moderator']
            : ['viewer', 'editor'];
        const currentRole = selectedAccess[module] || 'viewer';
        const enabled = typeof selectedAccess[module] === 'string';
        const checkbox = element('input', {attributes: {type: 'checkbox', ...(enabled ? {checked: 'checked'} : {})}});
        const select = element('select', {
            attributes: {'aria-label': `Rolle für ${label}`, ...(enabled ? {} : {disabled: 'disabled'})},
            children: roles.map((role) => element('option', {text: MODULE_ROLE_LABELS[role], attributes: {value: role}})),
        });
        select.value = roles.includes(currentRole) ? currentRole : 'viewer';
        checkbox.addEventListener('change', () => select.disabled = !checkbox.checked);

        return element('div', {
            className: 'module-access-row',
            attributes: {'data-module': module},
            children: [
                element('label', {className: 'check-field', children: [checkbox, element('span', {text: label})]}),
                select,
            ],
        });
    }),
});

const readModuleAccess = (form) => Object.fromEntries(
    [...form.querySelectorAll('[data-module]')]
        .filter((row) => row.querySelector('input[type="checkbox"]').checked)
        .map((row) => [row.dataset.module, row.querySelector('select').value]),
);

const pageAccessFields = (selectedAccess = null, pages = []) => {
    const restricted = element('input', {attributes: {type: 'checkbox', ...(selectedAccess !== null ? {checked: 'checked'} : {})}});
    const list = element('div', {className: 'page-access-list'});
    const flattenedPages = flattenPageTree(buildPageTree(pages));
    flattenedPages.forEach(({page, depth}) => {
        const selectedRole = selectedAccess?.[page.id] || 'editor';
        const enabled = typeof selectedAccess?.[page.id] === 'string';
        const checkbox = element('input', {attributes: {type: 'checkbox', ...(enabled ? {checked: 'checked'} : {})}});
        const role = element('select', {
            attributes: {'aria-label': `Seitenrecht für ${page.title}`, ...(enabled ? {} : {disabled: 'disabled'})},
            children: [
                element('option', {text: 'Editor', attributes: {value: 'editor'}}),
                element('option', {text: 'Publisher', attributes: {value: 'publisher'}}),
            ],
        });
        role.value = selectedRole;
        checkbox.addEventListener('change', () => role.disabled = !checkbox.checked);
        list.append(element('div', {
            className: 'page-access-row',
            attributes: {'data-page-access-id': page.id},
            children: [
                element('label', {className: 'check-field', children: [
                    checkbox,
                    element('span', {text: `${'— '.repeat(depth)}${page.title}`}),
                ]}),
                role,
            ],
        }));
    });
    if (!flattenedPages.length) list.append(emptyState('Noch keine Seiten vorhanden.'));
    list.hidden = !restricted.checked;
    restricted.addEventListener('change', () => list.hidden = !restricted.checked);

    return element('div', {className: 'page-access-scope', children: [
        element('label', {className: 'check-field', children: [restricted, element('span', {text: 'Zugriff auf bestimmte Seiten beschränken'})]}),
        element('small', {className: 'field-hint', text: 'Dann werden im Modul Seiten ausschließlich die ausgewählten Seiten angezeigt. Admin und Super-Admin behalten Vollzugriff.'}),
        list,
    ]});
};

const readPageAccess = (form) => {
    const scope = form.querySelector('.page-access-scope');
    const restricted = scope.querySelector(':scope > .check-field input').checked;
    const pageAccess = Object.fromEntries(
        [...scope.querySelectorAll('[data-page-access-id]')]
            .filter((row) => row.querySelector('input[type="checkbox"]').checked)
            .map((row) => [row.dataset.pageAccessId, row.querySelector('select').value]),
    );

    return {restricted, pageAccess};
};

const canEditUser = (user) => {
    if (!canEditModule('user_management')) return false;
    if (user.roles.includes('super_admin')) return hasAnyRole('super_admin');
    if (user.roles.includes('admin')) return hasAnyRole('admin', 'super_admin');

    return true;
};

const openUserAccessDialog = (user, refresh, pages) => {
    const dialog = element('dialog', {className: 'activity-dialog'});
    const message = formMessage();
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    const submit = element('button', {className: 'button', text: 'Zugriffsrechte speichern', attributes: {type: 'submit'}});
    const form = element('form', {className: 'activity-dialog-content', children: [
        element('header', {children: [
            element('p', {className: 'eyebrow', text: 'Rechtemanagement'}),
            element('h2', {text: user.displayName}),
            element('p', {text: user.email}),
        ]}),
        element('fieldset', {children: [
            element('legend', {text: 'Globale Administratorrolle'}),
            globalRoleField(user.roles[0] || ''),
            element('small', {className: 'field-hint', text: 'Admin und Super-Admin erhalten innerhalb der freigeschalteten Module alle Rechte.'}),
        ]}),
        element('fieldset', {children: [
            element('legend', {text: 'Module und Rollen'}),
            moduleAccessFields(user.moduleAccess || {}),
        ]}),
        element('fieldset', {children: [
            element('legend', {text: 'Seitenbezogene Rechte'}),
            pageAccessFields(user.pageAccess, pages),
        ]}),
        message,
        element('div', {className: 'confirm-dialog-actions', children: [cancel, submit]}),
    ]});
    cancel.addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        const data = new FormData(form);
        const globalRole = data.get('globalRole');
        const pageAccess = readPageAccess(form);
        try {
            await request(`/api/admin/v1/users/${user.id}/access`, {method: 'PUT', body: JSON.stringify({
                roles: globalRole ? [globalRole] : [],
                moduleAccess: readModuleAccess(form),
                pageAccessRestricted: pageAccess.restricted,
                pageAccess: pageAccess.pageAccess,
            })});
            toast('Globale Rolle und Modulrechte wurden gespeichert.');
            dialog.close();
            await refresh();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
            submit.disabled = false;
        }
    });
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(form);
    document.body.append(dialog);
    dialog.showModal();
};

const userCard = (user, refresh, pages) => {
    const roleLabel = user.roles[0] === 'super_admin' ? 'Super-Admin' : (user.roles[0] === 'admin' ? 'Admin' : 'Keine');
    const moduleLabels = Object.entries(user.moduleAccess || {}).map(([module, role]) => {
        const label = CMS_MODULES.find(([value]) => value === module)?.[1] || module;
        return `${label}: ${MODULE_ROLE_LABELS[role] || role}`;
    });
    const children = [
        element('header', {children: [element('strong', {text: user.displayName}), element('small', {text: user.active ? 'aktiv' : 'gesperrt'})]}),
        element('a', {text: user.email, attributes: {href: 'mailto:' + user.email}}),
        element('p', {className: 'tag-line', text: `Globale Rolle: ${roleLabel}`}),
        element('p', {className: 'tag-line', text: `Module: ${moduleLabels.join(' · ')}`}),
        ...(user.pageAccess !== null ? [element('p', {className: 'tag-line', text: `Seitenscope: ${Object.keys(user.pageAccess).length} ausgewählte Seiten`})] : []),
    ];
    if (canEditUser(user)) {
        const actions = [];
        const edit = element('button', {className: 'secondary-button', text: 'Zugriff bearbeiten', attributes: {type: 'button'}});
        edit.addEventListener('click', () => openUserAccessDialog(user, refresh, pages));
        actions.push(edit);
        if (user.active) {
            const suspend = element('button', {className: 'text-button danger', text: 'Benutzer sperren', attributes: {type: 'button'}});
            suspend.addEventListener('click', async () => {
                const confirmed = await confirmAction(
                    `„${user.displayName}“ sperren?`,
                    'Der Benutzer kann sich danach nicht mehr im Backend anmelden.',
                    'Benutzer sperren',
                );
                if (!confirmed) return;
                suspend.disabled = true;
                try {
                    await request(`/api/admin/v1/users/${user.id}/suspend`, {method: 'POST'});
                    toast('Benutzer wurde gesperrt.');
                    await refresh();
                } catch (error) {
                    toast(error.message, 'error');
                    suspend.disabled = false;
                }
            });
            actions.push(suspend);
        }
        children.push(element('div', {className: 'card-actions', children: actions}));
    }

    return element('article', {className: 'management-card', children});
};

const userCreationForm = (refresh, pages) => {
    const message = formMessage();
    const form = element('form', {className: 'compact-form', children: [
        element('h3', {text: 'Benutzer anlegen'}),
        element('div', {className: 'form-grid', children: [field('Name', 'displayName'), field('E-Mail', 'email', '', 'email'), field('Initialpasswort', 'password', '', 'password')]}),
        element('fieldset', {children: [
            element('legend', {text: 'Globale Administratorrolle'}),
            globalRoleField(),
            element('small', {className: 'field-hint', text: 'Optional. Die Modulfreigaben bleiben auch für Administratoren verbindlich.'}),
        ]}),
        element('fieldset', {children: [element('legend', {text: 'Module und Rollen'}), moduleAccessFields()]}),
        element('fieldset', {children: [element('legend', {text: 'Seitenbezogene Rechte'}), pageAccessFields(null, pages)]}),
        message,
        element('button', {className: 'button', text: 'Zugang anlegen', attributes: {type: 'submit'}}),
    ]});
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        const globalRole = data.get('globalRole');
        const pageAccess = readPageAccess(form);
        try {
            await request('/api/admin/v1/users', {method: 'POST', body: JSON.stringify({
                displayName: data.get('displayName'), email: data.get('email'), password: data.get('password'),
                roles: globalRole ? [globalRole] : [], moduleAccess: readModuleAccess(form),
                pageAccessRestricted: pageAccess.restricted, pageAccess: pageAccess.pageAccess,
            })});
            toast('Benutzerzugang wurde angelegt.');
            await refresh();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
    });
    return form;
};

if (app?.dataset.app === 'public') renderPublic();
if (app?.dataset.app === 'admin') renderAdmin().catch((error) => renderError(error.message));
