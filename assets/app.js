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
    if (!response.ok) throw new Error(data?.error?.message || data?.detail || data?.message || 'Die Anfrage ist fehlgeschlagen.');

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
        const remove = element('button', {className: 'text-button danger membership-remove-person', text: 'Person entfernen', attributes: {type: 'button'}});
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
                applicantField('E-Mail-Adresse (optional)', 'email', 'email', false),
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

const renderPublic = async () => {
    try {
        const slug = app.dataset.pageSlug;
        const [navigation, page] = await Promise.all([
            request('/api/public/v1/navigation'),
            request('/api/public/v1/pages/' + encodePageSlug(slug)),
        ]);
        updateDocumentMetadata(page);

        const renderNavigationItem = (item, nested = false) => {
            const active = treeContainsSlug(item, slug);
            const link = element('a', {
                className: item.slug === slug ? 'active' : '',
                text: item.label,
                attributes: {
                    href: pageHref(item.slug),
                    ...(item.slug === slug ? {'aria-current': 'page'} : {}),
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
        const navigationTree = buildPageTree(navigation.items, false);
        const links = navigationTree.map((item) => renderNavigationItem(item));

        const publicContext = {visited: new Set([page.id]), pagesById: null, showEmbedErrors: false, isPreview: false};
        const article = renderContentCard(page, publicContext);
        if (slug === 'kontakt') article.append(renderContactForm());
        if (slug === 'gaestebuch') article.append(await renderGuestbook());

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

        app.replaceChildren(
            header,
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
            element('footer', {
                className: 'site-footer',
                children: [
                    element('p', {text: '© ' + new Date().getFullYear() + ' Naturbad Borkheide e.V.'}),
                    element('nav', {attributes: {'aria-label': 'Servicenavigation'}, children: [
                        element('a', {text: 'Impressum', attributes: {href: '/seite/impressum'}}),
                        element('a', {text: 'Kontakt', attributes: {href: '/seite/kontakt'}}),
                        element('a', {text: 'Redaktion', attributes: {href: '/admin'}}),
                    ]}),
                ],
            }),
        );
    } catch (error) {
        renderError(error.message);
    }
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
            element('div', {className: 'event-helper-list', children: requests.map((requestItem) => {
                const actionButtons = [];
                if (canEditModule('event_helpers') && requestItem.status !== 'resolved') {
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
                }
                const intervals = Array.isArray(requestItem.participationIntervals) ? requestItem.participationIntervals : [];
                const selectedActivities = Array.isArray(requestItem.selectedActivities) ? requestItem.selectedActivities : [];
                const hasMessage = typeof requestItem.message === 'string' && requestItem.message.trim() !== '';
                const hasDetails = hasMessage || selectedActivities.length > 0 || intervals.length > 0;
                const detailsId = `event-helper-details-${requestItem.id}`;
                const identity = element('div', {className: 'event-helper-participant-identity', children: [
                    element('strong', {text: `${requestItem.firstName} ${requestItem.lastName}`}),
                    element('small', {text: `Angemeldet am ${new Date(requestItem.submittedAt).toLocaleString('de-DE')}`}),
                ]});
                const detailsBody = hasDetails ? element('div', {className: 'event-helper-participant-details', attributes: {id: detailsId, hidden: 'hidden'}, children: [
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
                ]}) : null;
                let identityControl = identity;
                if (detailsBody) {
                    identityControl = element('button', {
                        className: 'event-helper-participant-toggle',
                        attributes: {type: 'button', 'aria-expanded': 'false', 'aria-controls': detailsId},
                        children: [identity],
                    });
                    identityControl.addEventListener('click', () => {
                        const expanded = identityControl.getAttribute('aria-expanded') === 'true';
                        identityControl.setAttribute('aria-expanded', String(!expanded));
                        detailsBody.hidden = expanded;
                    });
                }
                return element('article', {className: `event-helper-participant status-${requestItem.status}`, children: [
                element('header', {className: 'event-helper-participant-header', children: [
                    identityControl,
                    element('div', {className: 'event-helper-participant-side', children: [
                        element('span', {className: `participant-status status-${requestItem.status}`, text: participationStatus(requestItem)}),
                        ...(actionButtons.length ? [element('div', {className: 'participant-actions', children: actionButtons})] : []),
                    ]}),
                ]}),
                ...(detailsBody ? [detailsBody] : []),
                ]});
            })}),
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
        const [household, payerCandidates] = await Promise.all([
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
                    await request(`/api/admin/v1/members/${member.id}`, {method: 'DELETE'});
                    toast('Das Mitglied wurde gelöscht.');
                    dialog.close();
                    await onSaved();
                } catch (error) {
                    toast(error.message, 'error');
                    deleteButton.disabled = false;
                }
            });
        }

        const memberExemptionReason = contributionExemptionReason(member ?? {});
        const contributionSection = member ? element('fieldset', {children: [
            element('legend', {text: 'Beitrag'}),
            element('p', {children: [
                element('strong', {text: memberExemptionReason
                    ?? (member.contributionCategory ? CONTRIBUTION_CATEGORY_LABELS[member.contributionCategory] || member.contributionCategory : 'Noch nicht berechnet')}),
                element('span', {text: ` · ${formatEuro(memberExemptionReason ? 0 : member.contributionAmountCents)} pro Jahr`}),
            ]}),
            ...(!memberExemptionReason && member.workAssignmentSurchargeCents != null ? [element('p', {children: [
                element('strong', {text: 'Arbeitseinsatz-Zuschlag'}),
                element('span', {text: ` · zzgl. ${formatEuro(member.workAssignmentSurchargeCents)} pro Jahr (bei Ableistung der Gemeinschaftsstunden erstattungsfähig)`}),
            ]})] : []),
            element('p', {className: 'field-hint', text: 'Erhaltene Beitragssätze (einmalige Gebühren):'}),
            element('ul', {className: 'member-remarks', children: member.oneTimeCharges.length
                ? member.oneTimeCharges.map((charge) => element('li', {children: [
                    element('strong', {text: charge.label}),
                    element('span', {text: ` · ${formatEuro(charge.amountCents)} · ${new Date(charge.chargedAt).toLocaleDateString('de-DE')}`}),
                ]}))
                : [element('li', {className: 'empty-copy', text: 'Keine einmaligen Gebühren berechnet.'})]}),
        ]}) : null;
        const recalculate = member ? element('button', {className: 'secondary-button', text: 'Beitrag neu berechnen', attributes: {type: 'button'}}) : null;
        if (recalculate) {
            recalculate.addEventListener('click', async () => {
                recalculate.disabled = true;
                try {
                    await request(`/api/admin/v1/members/${member.id}/recalculate-contribution`, {method: 'POST'});
                    toast('Der Beitrag wurde für die ganze Familie neu berechnet.');
                    dialog.close();
                    await onSaved();
                } catch (error) {
                    toast(error.message, 'error');
                    recalculate.disabled = false;
                }
            });
            contributionSection.append(
                recalculate,
                element('small', {text: 'Berechnet den Beitrag für die gesamte Familie (alle Mitglieder mit derselben Hauptnummer) neu.'}),
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

        const payerSection = member ? element('fieldset', {children: [
            element('legend', {text: 'Gesamtberechnung für den Zahler'}),
            element('p', {className: 'field-hint', text: household.payer.id === member.id
                ? 'Dieses Mitglied zahlt selbst, zusammen für:'
                : `Der Beitrag wird gezahlt von ${household.payer.firstName} ${household.payer.lastName} (${household.payer.memberNumber}), zusammen für:`}),
            element('ul', {className: 'member-payer-list', children: household.payerEntries.map((entry) => payerListItem(entry, member.id, openOther))}),
            element('div', {className: 'member-payer-total', children: [
                element('span', {text: 'Gesamtbeitrag pro Jahr'}),
                // Aus denselben Positionen wie die Liste darüber berechnet (statt aus dem vom
                // Server gelieferten Rohwert), damit ein gerade erst gesetztes „Beitragspflichtig“-
                // Häkchen die Summe sofort auf 0 € zieht, statt erst nach „Beitrag neu berechnen“.
                element('span', {text: formatEuro(household.payerEntries.reduce(
                    (sum, entry) => sum + contributionPositions(entry).reduce((entrySum, [, cents]) => entrySum + (cents || 0), 0),
                    0,
                ))}),
            ]}),
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
                    element('small', {text: 'Vorstandsmitglieder sind laut Satzung beitragsfrei: Häkchen entfernen, um Konto-, Bank- und Zahlungsdaten zu sperren. Der Beitrag wird dann bei „Beitrag neu berechnen“ mit 0 € und dem Hinweis „Vorstand“ geführt.'}),
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
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            submit.disabled = true;
            const payload = {
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
            };
            try {
                if (member) {
                    await request(`/api/admin/v1/members/${member.id}`, {
                        method: 'PUT',
                        body: JSON.stringify({...payload, memberNumber: member.memberNumber, version: member.version}),
                    });
                    toast('Das Mitglied wurde gespeichert.');
                } else {
                    await request('/api/admin/v1/members', {method: 'POST', body: JSON.stringify(payload)});
                    toast('Das Mitglied wurde angelegt.');
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

    let memberSearchTerm = '';
    let memberSortField = 'memberNumber';
    let memberSortDirection = 'asc';
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
            actions.push(actionButton('Beiträge für alle Mitglieder neu berechnen', '/api/admin/v1/members/recalculate-contributions', showMembershipManagement, 'secondary-button', {
                confirm: {
                    title: 'Beiträge neu berechnen',
                    description: 'Der Beitrag wird für alle Mitglieder anhand der aktuellen Beitragssätze und Altersspannen neu berechnet. Das kann nicht rückgängig gemacht werden.',
                    label: 'Neu berechnen',
                },
                success: 'Die Beiträge wurden für alle Mitglieder neu berechnet.',
            }));
        }
        actions.push(actionMenu('Datenbank', [
            {label: 'Importieren …', run: () => openMemberImportDialog(showMembershipManagement)},
            {label: 'Exportieren …', run: () => openMemberExportDialog()},
        ]));

        const search = searchField('Volltextsuche: Nummer, Name, Straße, PLZ, Ort, E-Mail, Telefon …', memberSearchTerm, async (value) => {
            memberSearchTerm = value;
            await showMembershipManagement();
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
            const sortedItems = [...data.items].sort((left, right) => {
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
            element('div', {className: 'management-toolbar', children: [search, element('span', {text: `${data.total} Mitglieder`})]}),
            data.items.length ? table : emptyState('Noch keine Mitglieder angelegt.'),
        );
    };

    const openContributionRateDialog = (rate, existingRates, onSaved) => {
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

    const showContributionRates = async () => {
        const data = await request('/api/admin/v1/contribution-rates');
        const heading = sectionHeading('Beitragssätze', 'Bezeichnung, Betrag, Zeitraum und Altersspanne je Beitragssatz pflegen');
        if (canEditModule('contribution_rates')) {
            const create = element('button', {className: 'button', text: '＋ Neuer Beitragssatz', attributes: {type: 'button'}});
            create.addEventListener('click', () => openContributionRateDialog(null, data.items, showMembershipManagement));
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
                        ].filter(Boolean).join(' · ')}),
                    ]}),
                    element('span', {className: 'status-badge', text: `${formatEuro(rate.amountCents)} · ${PAYMENT_INTERVAL_LABELS[rate.period] || rate.period}`}),
                    ...(isOnce ? [] : [element('span', {className: 'status-badge', text: `${formatEuro(rate.annualAmountCents)} / Jahr`})]),
                    ...(canEditModule('contribution_rates') ? [element('span', {className: 'activity-list-edit', text: 'Bearbeiten ›'})] : []),
                ],
            });
            if (canEditModule('contribution_rates')) row.addEventListener('click', () => openContributionRateDialog(rate, data.items, showMembershipManagement));

            return row;
        });
        workspace.replaceChildren(
            heading,
            element('div', {className: 'activity-list', children: rows.length ? rows : [emptyState('Keine Beitragssätze vorhanden.')]}),
        );
    };

    let activeMembershipTab = null;
    const showMembershipManagement = async () => {
        const tabs = [
            ...(hasModule('members') ? [['dashboard', 'Dashboard', showMembershipDashboard]] : []),
            ...(hasModule('members') ? [['members', 'Mitglieder', showMembers]] : []),
            ...(hasModule('contribution_rates') ? [['rates', 'Beitragssätze', showContributionRates]] : []),
            ...(hasModule('membership_applications') ? [['applications', 'Mitgliedsanträge', showMembership]] : []),
        ];
        if (!tabs.some(([key]) => key === activeMembershipTab)) activeMembershipTab = tabs[0]?.[0] || null;

        const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'Mitgliederverwaltung'}, children: tabs.map(([key, label]) => {
            const button = element('button', {
                className: `sub-tab${key === activeMembershipTab ? ' active' : ''}`,
                text: label,
                attributes: {type: 'button'},
            });
            button.addEventListener('click', async () => {
                activeMembershipTab = key;
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

    const showUsers = async () => {
        const [data, pageOptions] = await Promise.all([
            request('/api/admin/v1/users'),
            request('/api/admin/v1/users/page-options'),
        ]);
        workspace.replaceChildren(
            sectionHeading('Benutzer', 'Zugänge und Rollen verwalten'),
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

    let activeEventTab = null;
    const showEventManagement = async () => {
        const tabs = [
            ...(hasModule('events') ? [['events', 'Veranstaltungen', showEvents]] : []),
            ...(hasModule('event_helpers') ? [['helpers', 'Veranstaltungshelfer', showEventHelpers]] : []),
            ...(hasModule('activities') ? [['activities', 'Aktivitäten', showActivities]] : []),
        ];
        if (!tabs.some(([key]) => key === activeEventTab)) activeEventTab = tabs[0]?.[0] || null;

        const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'Veranstaltung'}, children: tabs.map(([key, label]) => {
            const button = element('button', {
                className: `sub-tab${key === activeEventTab ? ' active' : ''}`,
                text: label,
                attributes: {type: 'button'},
            });
            button.addEventListener('click', async () => {
                activeEventTab = key;
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

    const addMenu = (label, action) => {
        const button = element('button', {className: 'admin-menu-item', text: label, attributes: {type: 'button'}});
        button.addEventListener('click', async () => {
            menu.querySelectorAll('button').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            closeAdminNavigation();
            try { await action(); } catch (error) {
                toast(error.message, 'error');
                workspace.replaceChildren(emptyState(error.message));
            }
        });
        menu.append(button);
        return button;
    };
    const menuItems = [];
    if (hasModule('pages')) menuItems.push(addMenu('Seiten', showPages));

    if (hasModule('events') || hasModule('event_helpers') || hasModule('activities')) {
        menuItems.push(addMenu('Veranstaltung', showEventManagement));
    }

    if (hasModule('members') || hasModule('contribution_rates') || hasModule('membership_applications')) {
        menuItems.push(addMenu('Mitgliederverwaltung', showMembershipManagement));
    }

    if (hasModule('user_management')) menuItems.push(addMenu('Benutzer', showUsers));

    if (hasModule('guestbook')) menuItems.push(addMenu('Gästebuch', showGuestbook));
    if (hasModule('contact_requests')) menuItems.push(addMenu('Kontaktanfragen', showContact));

    const logout = element('button', {className: 'text-button', text: 'Abmelden', attributes: {type: 'button'}});
    logout.addEventListener('click', async () => {
        await request('/api/auth/v1/logout', {method: 'POST'});
        csrfToken = null;
        currentRoles = [];
        currentModuleAccess = {};
        currentPageAccess = null;
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
    if (menuItems.length) {
        menuItems[0].click();
    } else {
        workspace.replaceChildren(emptyState('Für diesen Zugang ist kein Redaktionsmodul freigeschaltet.'));
    }
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
