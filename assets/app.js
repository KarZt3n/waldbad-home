import './styles/app.css';

const app = document.querySelector('#app');
let csrfToken = null;
let currentRoles = [];

const BLOCK_TYPES = {
    heading: 'Überschrift',
    rich_text: 'Text',
    image: 'Bild',
    image_text: 'Bild + Text',
    alert: 'Hinweis',
    call_to_action: 'Handlungsaufruf',
    custom_html: 'Eigenes HTML',
    embedded_page: 'Seite einbetten',
    event: 'Veranstaltung',
    event_reference: 'Veranstaltung einbetten',
    extension: 'Erweiterung',
};

const createBlock = (type) => ({
    type,
    content: '',
    mediaUrl: null,
    mediaAlt: null,
    linkUrl: null,
    linkLabel: null,
    layout: ['image_text', 'event_reference'].includes(type) ? 'image_left' : (type === 'image' ? 'center' : null),
    imageWidthPercent: ['image_text', 'event_reference'].includes(type) ? 50 : (type === 'image' ? 100 : null),
    verticalAlignment: ['image_text', 'event_reference'].includes(type) ? 'center' : null,
    textAlignment: ['image_text', 'event_reference'].includes(type) ? 'left' : null,
    imageFit: ['image_text', 'event_reference'].includes(type) ? 'cover' : null,
    embeddedPageId: null,
    eventTitle: type === 'event' ? '' : null,
    eventDate: type === 'event' ? '' : null,
    eventTime: type === 'event' ? '14:00' : null,
    eventIdentifier: type === 'event' ? crypto.randomUUID() : null,
    eventHelpEnabled: type === 'event',
    eventHelpButtonLabel: type === 'event' ? 'Ich möchte helfen!' : null,
    extensionKey: type === 'extension' ? 'membership_application' : null,
});

const hasAnyRole = (...roles) => currentRoles.some((role) => roles.includes(role));
const canEdit = () => hasAnyRole('editor', 'publisher', 'admin', 'super_admin');
const canPublish = () => hasAnyRole('publisher', 'admin', 'super_admin');
const canModerate = () => hasAnyRole('moderator', 'admin', 'super_admin');
const canManageUsers = () => hasAnyRole('admin', 'super_admin');
const canManageMembership = () => hasAnyRole('admin', 'super_admin');

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

const pageHref = (slug) => slug === 'startseite' ? '/' : '/seite/' + encodeURIComponent(slug);
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
    const membershipType = element('select', {attributes: {name: 'membershipType', id: `${instanceId}-type`}, children: [
        element('option', {text: 'Einzelmitgliedschaft', attributes: {value: 'individual'}}),
        element('option', {text: 'Familienmitgliedschaft', attributes: {value: 'family'}}),
    ]});
    const addPerson = element('button', {className: 'secondary-button', text: '＋ Weitere Person', attributes: {type: 'button'}});

    const applicantField = (label, key, type = 'text', required = true) => {
        const wrapper = field(label, `${instanceId}-${key}-${applicants.children.length}`, '', type);
        const input = wrapper.querySelector('input');
        input.dataset.applicantField = key;
        if (required) input.required = true;
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
        addPerson.disabled = membershipType.value !== 'family' || applicants.children.length >= 8;
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
    membershipType.addEventListener('change', () => {
        if (membershipType.value === 'individual' && applicants.children.length > 1) {
            membershipType.value = 'family';
            toast('Eine Einzelmitgliedschaft kann nur eine Person enthalten. Entferne zuerst die weiteren Personen.', 'error');
        }
        refreshApplicantCards();
    });
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
        element('label', {className: 'field', children: [element('span', {text: 'Art der Mitgliedschaft'}), membershipType]}),
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
                membershipType: data.get('membershipType'),
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

const openEventHelpDialog = (block) => {
    const dialog = element('dialog', {className: 'event-help-dialog'});
    const message = formMessage();
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Helferanmeldung schließen'}});
    const privacy = element('input', {attributes: {name: 'privacyAccepted', type: 'checkbox', required: 'required'}});
    const form = element('form', {className: 'public-form event-help-form', children: [
        element('header', {children: [
            element('p', {className: 'eyebrow', text: 'Helferanmeldung'}),
            element('h2', {text: block.eventTitle || 'Veranstaltung'}),
            element('p', {text: 'Schön, dass du uns unterstützen möchtest. Teile uns kurz mit, wobei du helfen kannst.'}),
        ]}),
        element('div', {className: 'form-grid', children: [field('Vorname', 'firstName'), field('Nachname', 'lastName')]}),
        field('Nachricht / Wobei möchtest du helfen? (optional)', 'message', '', 'textarea'),
        element('label', {className: 'check-field', children: [privacy, element('span', {text: 'Ich stimme der Verarbeitung meiner Angaben zur Organisation dieser Veranstaltung zu.'})]}),
        message,
        element('button', {className: 'button', text: 'Helferanmeldung absenden', attributes: {type: 'submit'}}),
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
                privacyAccepted: data.get('privacyAccepted') === 'on',
            })});
            toast(response.message);
            dialog.close();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
            submit.disabled = false;
        }
    });
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(close, form);
    document.body.append(dialog);
    dialog.showModal();
    form.querySelector('[name="firstName"]').focus();
};

const renderPublicBlock = (block, context = {visited: new Set(), pagesById: null, showEmbedErrors: false, isPreview: false}) => {
    if (block.type === 'extension' && block.extensionKey === 'membership_application') {
        return renderMembershipApplicationForm(context.isPreview === true);
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
        if (block.eventHelpEnabled && block.eventIdentifier) {
            const help = element('button', {
                className: 'button event-help-button',
                text: block.eventHelpButtonLabel || 'Ich möchte helfen!',
                attributes: {type: 'button', ...(context.isPreview ? {disabled: 'disabled', title: 'In der Vorschau nicht verfügbar'} : {})},
            });
            if (!context.isPreview) help.addEventListener('click', () => openEventHelpDialog(block));
            copy.append(help);
        }
        const layout = ['image_left', 'image_right', 'image_top'].includes(block.layout) ? block.layout : 'image_left';
        const imageWidth = Number.isInteger(block.imageWidthPercent) ? block.imageWidthPercent : 32;
        const verticalAlignment = ['top', 'center', 'bottom'].includes(block.verticalAlignment) ? block.verticalAlignment : 'center';
        const textAlignment = ['left', 'center', 'right'].includes(block.textAlignment) ? block.textAlignment : 'left';
        const imageFit = ['cover', 'contain'].includes(block.imageFit) ? block.imageFit : 'cover';
        return element('article', {
            className: `event-block${block.mediaUrl ? ` has-image ${layout} align-${verticalAlignment} text-${textAlignment} fit-${imageFit}` : ''}`,
            attributes: {style: `--event-image-width: ${imageWidth}%`},
            children: [
            ...(block.mediaUrl ? [element('img', {attributes: {src: block.mediaUrl, alt: block.mediaAlt || '', loading: 'lazy'}})] : []),
            copy,
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
        const media = block.linkUrl
            ? element('a', {
                className: 'image-text-media',
                attributes: {href: block.linkUrl, target: '_blank', rel: 'noopener noreferrer'},
                children: [image],
            })
            : element('div', {className: 'image-text-media', children: [image]});
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

const renderPublic = async () => {
    try {
        const slug = app.dataset.pageSlug;
        const [navigation, page] = await Promise.all([
            request('/api/public/v1/navigation'),
            request('/api/public/v1/pages/' + encodeURIComponent(slug)),
        ]);
        document.title = (page.seoTitle || page.title) + ' – Waldbad Borkheide';

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
                text: '⌄',
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

        app.replaceChildren(
            element('header', {
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
                    element('nav', {className: 'main-nav', attributes: {'aria-label': 'Hauptnavigation'}, children: links}),
                ],
            }),
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
        rows.focus();
        rows.select();
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
                choose,
            ]});
        }));
    } catch (error) {
        content.replaceChildren(emptyState(error.message));
    }
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
    if (block.type === 'event') {
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
        helpLabel.hidden = !helpEnabled.checked;
        helpEnabled.addEventListener('change', () => {
            block.eventHelpEnabled = helpEnabled.checked;
            if (helpEnabled.checked && !block.eventIdentifier) block.eventIdentifier = crypto.randomUUID();
            helpLabel.hidden = !helpEnabled.checked;
        });
        helpLabelInput.addEventListener('input', () => block.eventHelpButtonLabel = helpLabelInput.value || 'Ich möchte helfen!');
        card.append(
            element('label', {className: 'check-field event-help-option', children: [
                helpEnabled,
                element('span', {text: 'Im Frontend den Button „Ich möchte helfen!“ mit Anmeldeformular anzeigen'}),
            ]}),
            helpLabel,
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

    if (block.type === 'image' || block.type === 'image_text' || block.type === 'event' || block.type === 'event_reference') {
        const optionalMedia = block.type === 'event' || block.type === 'event_reference';
        const media = field(optionalMedia ? 'Bild-URL (optional)' : 'Bild-URL', 'block-media-' + index, block.mediaUrl || '');
        const alt = field('Alternativtext (optional; leer = dekorativ)', 'block-alt-' + index, block.mediaAlt || '');
        const mediaInput = media.querySelector('input');
        mediaInput.addEventListener('input', (event) => block.mediaUrl = event.target.value || null);
        alt.querySelector('input').addEventListener('input', (event) => block.mediaAlt = event.target.value || null);
        const uploadInput = element('input', {attributes: {type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', hidden: 'hidden'}});
        const uploadButton = element('button', {className: 'secondary-button', text: 'Bild hochladen', attributes: {type: 'button'}});
        const selectButton = element('button', {className: 'secondary-button', text: 'Bild auswählen', attributes: {type: 'button'}});
        const uploadMessage = element('small', {className: 'upload-message', attributes: {'aria-live': 'polite'}});
        uploadButton.addEventListener('click', () => uploadInput.click());
        selectButton.addEventListener('click', () => openImagePicker((image) => {
            block.mediaUrl = image.url;
            mediaInput.value = image.url;
            uploadMessage.textContent = `${image.originalName} wurde ausgewählt.`;
            toast(`${image.originalName} wurde ausgewählt.`);
        }));
        uploadInput.addEventListener('change', async () => {
            const image = uploadInput.files?.[0];
            if (!image) return;
            const body = new FormData();
            body.append('image', image);
            uploadButton.disabled = true;
            uploadMessage.textContent = 'Bild wird hochgeladen …';
            try {
                const stored = await request('/api/admin/v1/media/images', {method: 'POST', body});
                block.mediaUrl = stored.url;
                mediaInput.value = stored.url;
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
            ...(block.type === 'event_reference' ? [element('small', {text: 'Ohne eigenes Bild wird das Bild der ausgewählten Veranstaltung verwendet.'})] : []),
        );
    }
    if (block.type === 'image_text') {
        const imageLink = field('Linkziel des Bildes (optional)', 'block-image-link-' + index, block.linkUrl || '', 'url');
        imageLink.querySelector('input').addEventListener('input', (event) => block.linkUrl = event.target.value || null);
        card.append(imageLink);

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
        parentId: data.get('parentId') || null,
        navigationPosition: Number(data.get('navigationPosition')),
        visible: data.get('visible') === 'on',
        showInNavigation: data.get('showInNavigation') === 'on',
        seoTitle: data.get('seoTitle') || null,
        seoDescription: data.get('seoDescription') || null,
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

const pageEditor = (page, onSaved, pages = [], initialParentId = null) => {
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
    if (page && canEdit() && page.status === 'draft') {
        const review = element('button', {className: 'secondary-button', text: 'Zur Prüfung', attributes: {type: 'button'}});
        review.addEventListener('click', () => runStatusAction('request-review', true));
        statusActions.append(review);
    }
    if (page && canPublish() && page.status !== 'published' && page.status !== 'archived') {
        const publish = element('button', {className: 'button', text: 'Veröffentlichen', attributes: {type: 'button'}});
        publish.addEventListener('click', () => runStatusAction('publish', true));
        statusActions.append(publish);
    }
    if (page && canPublish() && page.publishedAt && page.status !== 'archived') {
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
    if (!canEdit() || page?.status === 'archived') saveButton.disabled = true;
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
    const navigationLabelInput = form.querySelector('[name="navigationLabel"]');
    const seoTitleInput = form.querySelector('[name="seoTitle"]');
    let updateSlugAutomatically = !page || page.slug === slugify(page.title);
    let updateNavigationAutomatically = !page || page.navigationLabel === page.title;
    let updateSeoTitleAutomatically = !page || !page.seoTitle || page.seoTitle === page.title;
    slugInput.addEventListener('input', () => updateSlugAutomatically = false);
    navigationLabelInput.addEventListener('input', () => updateNavigationAutomatically = false);
    seoTitleInput.addEventListener('input', () => updateSeoTitleAutomatically = false);
    titleInput.addEventListener('input', () => {
        if (updateSlugAutomatically) slugInput.value = slugify(titleInput.value);
        if (updateNavigationAutomatically) navigationLabelInput.value = titleInput.value.trim();
        if (updateSeoTitleAutomatically) seoTitleInput.value = titleInput.value.trim();
    });
    form.querySelector('[name="visible"]').checked = page?.visible ?? true;
    form.querySelector('[name="showInNavigation"]').checked = page?.showInNavigation ?? true;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = pagePayload(form, blocks, page);
        try {
            await request(page ? '/api/admin/v1/pages/' + page.id : '/api/admin/v1/pages', {
                method: page ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });
            message.textContent = 'Entwurf gespeichert.';
            toast(page ? 'Entwurf wurde gespeichert.' : 'Seite wurde angelegt.');
            await onSaved();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
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
    const workspace = element('section', {className: 'admin-workspace'});
    const sidebarTitle = element('h1', {text: 'Redaktion'});
    const menu = element('nav', {className: 'admin-menu', attributes: {'aria-label': 'Redaktionsbereiche'}});

    const showPages = async () => {
        const pages = await request('/api/admin/v1/pages');
        const list = element('div', {className: 'management-list page-tree-panel'});
        if (canEdit()) {
            const create = element('button', {className: 'secondary-button full', text: '＋ Neue Hauptseite', attributes: {type: 'button'}});
            create.addEventListener('click', () => workspace.replaceChildren(pageEditor(null, showPages, pages.items)));
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
            title.addEventListener('click', () => workspace.replaceChildren(pageEditor(page, showPages, pages.items)));

            const actions = [];
            if (canEdit()) {
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
                const addChild = element('button', {className: 'tree-icon-button', text: '＋', attributes: {type: 'button', title: `Unterseite zu ${page.title} hinzufügen`, 'aria-label': `Unterseite zu ${page.title} hinzufügen`}});
                addChild.addEventListener('click', () => workspace.replaceChildren(pageEditor(null, showPages, pages.items, page.id)));
                const edit = element('button', {className: 'tree-icon-button', text: '✎', attributes: {type: 'button', title: `${page.title} bearbeiten`, 'aria-label': `${page.title} bearbeiten`}});
                edit.addEventListener('click', () => workspace.replaceChildren(pageEditor(page, showPages, pages.items)));
                const duplicate = element('button', {className: 'tree-icon-button', text: '⧉', attributes: {type: 'button', title: `${page.title} duplizieren`, 'aria-label': `${page.title} duplizieren`}});
                duplicate.addEventListener('click', () => runPageAction(
                    duplicate,
                    `/api/admin/v1/pages/${page.id}/duplicate`,
                    'POST',
                    `„${page.title}“ wurde als ausgeblendeter Entwurf dupliziert.`,
                ));
                const moveUp = element('button', {className: 'tree-icon-button', text: '↑', attributes: {type: 'button', title: `${page.title} nach oben verschieben`, 'aria-label': `${page.title} nach oben verschieben`}});
                moveUp.disabled = siblingIndex === 0;
                moveUp.addEventListener('click', () => runPageAction(
                    moveUp,
                    `/api/admin/v1/pages/${page.id}/move/up`,
                    'POST',
                    `„${page.title}“ wurde nach oben verschoben.`,
                ));
                const moveDown = element('button', {className: 'tree-icon-button', text: '↓', attributes: {type: 'button', title: `${page.title} nach unten verschieben`, 'aria-label': `${page.title} nach unten verschieben`}});
                moveDown.disabled = siblingIndex === siblings.length - 1;
                moveDown.addEventListener('click', () => runPageAction(
                    moveDown,
                    `/api/admin/v1/pages/${page.id}/move/down`,
                    'POST',
                    `„${page.title}“ wurde nach unten verschoben.`,
                ));
                const remove = element('button', {className: 'tree-icon-button danger', text: '✕', attributes: {type: 'button', title: `${page.title} löschen`, 'aria-label': `${page.title} löschen`}});
                remove.addEventListener('click', async () => {
                    const confirmed = await confirmAction(
                        `„${page.title}“ löschen?`,
                        'Die Seite und ihre Inhalte werden dauerhaft gelöscht. Seiten mit Unterseiten oder Einbettungen können erst gelöscht werden, nachdem diese Abhängigkeiten entfernt wurden.',
                        'Seite löschen',
                    );
                    if (!confirmed) return;
                    await runPageAction(remove, `/api/admin/v1/pages/${page.id}`, 'DELETE', `„${page.title}“ wurde gelöscht.`);
                });
                actions.push(addChild, edit, duplicate, moveUp, moveDown, remove);
            }

            const row = element('div', {className: 'page-tree-row', children: [title, element('div', {className: 'page-tree-actions', children: actions})]});
            const item = element('li', {className: `page-tree-node${page.visible ? '' : ' is-hidden'}`, children: [row]});
            if (page.children.length) {
                const children = element('ul', {className: 'page-tree-children', children: page.children.map(renderTreeNode)});
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
        const tree = buildPageTree(pages.items);
        list.append(tree.length
            ? element('ul', {className: 'page-tree', children: tree.map(renderTreeNode)})
            : emptyState('Noch keine Seiten vorhanden.'));
        workspace.replaceChildren(sectionHeading('Seitenstruktur', 'Hauptseiten und Unterseiten verwalten'), list);
    };

    const showGuestbook = async () => {
        const data = await request('/api/admin/v1/guestbook-entries');
        workspace.replaceChildren(sectionHeading('Gästebuch', 'Neue Einträge prüfen und moderieren'), element('div', {
            className: 'card-list', children: data.items.length ? data.items.map((entry) => moderationCard(entry, showGuestbook)) : [emptyState('Keine Gästebucheinträge vorhanden.')],
        }));
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
            await showEventHelpers();
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
            intervalList.querySelector('[data-interval-from]').focus();
        };
        const participationStatus = (requestItem) => {
            if (requestItem.status === 'participated') {
                const hours = new Intl.NumberFormat('de-DE', {maximumFractionDigits: 2}).format(requestItem.participationMinutes / 60);
                return `hat teilgenommen · gesamt ${hours} Stunden`;
            }
            if (requestItem.status === 'not_participated') return 'nicht teilgenommen · 0 Stunden';
            if (requestItem.status === 'resolved') return 'erledigt';
            return 'neu';
        };
        const groups = new Map();
        data.items.forEach((item) => {
            if (!groups.has(item.eventIdentifier)) groups.set(item.eventIdentifier, {event: item, requests: []});
            groups.get(item.eventIdentifier).requests.push(item);
        });
        const sortedGroups = [...groups.values()].sort((first, second) => {
            const dateComparison = second.event.eventDate.localeCompare(first.event.eventDate);
            if (dateComparison !== 0) return dateComparison;

            return second.event.eventTime.localeCompare(first.event.eventTime);
        });
        const cards = sortedGroups.map(({event, requests}) => {
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
                if (requestItem.status !== 'resolved') {
                    const participated = element('button', {
                        className: 'button',
                        text: requestItem.status === 'participated' ? 'Zeiten bearbeiten' : 'Hat teilgenommen',
                        attributes: {type: 'button'},
                    });
                    participated.addEventListener('click', () => openParticipationDialog(requestItem));
                    actionButtons.push(participated);
                    if (requestItem.status !== 'not_participated') {
                        const absent = element('button', {className: 'secondary-button', text: 'Nicht teilgenommen', attributes: {type: 'button'}});
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
                return element('article', {className: `management-card status-${requestItem.status}`, children: [
                element('header', {children: [
                    element('strong', {text: `${requestItem.firstName} ${requestItem.lastName}`}),
                    element('small', {text: `${participationStatus(requestItem)} · ${new Date(requestItem.submittedAt).toLocaleString('de-DE')}`}),
                ]}),
                ...(typeof requestItem.message === 'string' && requestItem.message.trim() !== ''
                    ? [element('p', {text: requestItem.message})]
                    : []),
                ...(intervals.length ? [element('ul', {className: 'participation-interval-summary', children: intervals.map((interval) => element('li', {text: `${interval.fromTime}–${interval.toTime} Uhr`}))})] : []),
                ...(actionButtons.length ? [element('div', {className: 'card-actions', children: actionButtons})] : []),
                ]});
            })}),
            ]});
        });
        workspace.replaceChildren(
            sectionHeading('Veranstaltungshelfer', 'Anmeldungen nach Veranstaltung gruppiert verwalten'),
            element('div', {className: 'event-helper-groups', children: cards.length ? cards : [emptyState('Noch keine Helferanmeldungen vorhanden.')]}),
        );
    };

    let membershipStatusFilter = '';
    const showMembership = async () => {
        const query = membershipStatusFilter ? `?status=${encodeURIComponent(membershipStatusFilter)}` : '';
        const data = await request('/api/admin/v1/membership-applications' + query);
        const statusLabels = {pending: 'Offen', processing: 'In Übertragung', done: 'Übernommen', failed: 'Fehlgeschlagen'};
        const filter = element('select', {attributes: {'aria-label': 'Mitgliedsanträge nach Status filtern'}, children: [
            element('option', {text: 'Alle Status', attributes: {value: ''}}),
            ...Object.entries(statusLabels).map(([value, label]) => element('option', {text: label, attributes: {value}})),
        ]});
        filter.value = membershipStatusFilter;
        filter.addEventListener('change', async () => {
            membershipStatusFilter = filter.value;
            await showMembership();
        });
        const cards = data.items.map((application) => {
            const primary = application.applicants[0];
            const people = application.applicants.map((person) => element('li', {children: [
                element('strong', {text: `${person.firstName} ${person.lastName}`}),
                element('span', {text: ` · ${new Date(`${person.birthDate}T00:00:00`).toLocaleDateString('de-DE')} · ${person.street} ${person.houseNumber}, ${person.postalCode} ${person.city}`}),
                ...(person.email ? [element('a', {text: ` · ${person.email}`, attributes: {href: `mailto:${person.email}`}})] : []),
                ...(person.phone ? [element('a', {text: ` · ${person.phone}`, attributes: {href: `tel:${person.phone}`}})] : []),
            ]}));
            const actions = [];
            if (application.status === 'failed') {
                actions.push(actionButton('Erneut zur Übertragung freigeben', `/api/admin/v1/membership-applications/${application.id}/retry`, showMembership, 'button', {
                    success: 'Der Antrag steht erneut zur Übertragung bereit.',
                }));
            }
            return element('article', {className: `management-card membership-admin-card status-${application.status}`, children: [
                element('header', {children: [
                    element('div', {children: [
                        element('strong', {text: primary ? `${primary.firstName} ${primary.lastName}` : application.id}),
                        element('small', {text: `${application.membershipType === 'family' ? 'Familie' : 'Einzelperson'} · ${application.applicants.length} ${application.applicants.length === 1 ? 'Person' : 'Personen'}`}),
                    ]}),
                    element('span', {className: `status-badge status-${application.status}`, text: statusLabels[application.status] || application.status}),
                ]}),
                element('dl', {className: 'membership-meta', children: [
                    element('div', {children: [element('dt', {text: 'Eingang'}), element('dd', {text: new Date(application.submittedAt).toLocaleString('de-DE')})]}),
                    element('div', {children: [element('dt', {text: 'Kontoinhaber'}), element('dd', {text: application.accountHolder})]}),
                    element('div', {children: [element('dt', {text: 'IBAN'}), element('dd', {text: application.iban})]}),
                    element('div', {children: [element('dt', {text: 'Vorgang'}), element('dd', {text: application.id})]}),
                    ...(application.externalReference ? [element('div', {children: [element('dt', {text: 'Fremdsystem'}), element('dd', {text: application.externalReference})]})] : []),
                ]}),
                element('details', {children: [
                    element('summary', {text: 'Personen und Kontaktdaten anzeigen'}),
                    element('ol', {className: 'membership-admin-people', children: people}),
                ]}),
                ...(application.failureReason ? [element('p', {className: 'failure-message', text: application.failureReason})] : []),
                ...(actions.length ? [element('div', {className: 'card-actions', children: actions})] : []),
            ]});
        });
        workspace.replaceChildren(
            sectionHeading('Mitgliedsanträge', 'Offene Anträge prüfen und die Übergabe an das Fremdsystem überwachen'),
            element('div', {className: 'management-toolbar', children: [filter, element('span', {text: `${data.total} Anträge`})]}),
            element('div', {className: 'card-list', children: cards.length ? cards : [emptyState('Keine Mitgliedsanträge für diesen Status vorhanden.')]}),
        );
    };

    const showUsers = async () => {
        const data = await request('/api/admin/v1/users');
        workspace.replaceChildren(sectionHeading('Benutzer', 'Zugänge und Rollen verwalten'), userCreationForm(showUsers), element('div', {
            className: 'card-list', children: data.items.map(userCard),
        }));
    };

    const addMenu = (label, action) => {
        const button = element('button', {className: 'admin-menu-item', text: label, attributes: {type: 'button'}});
        button.addEventListener('click', async () => {
            menu.querySelectorAll('button').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            try { await action(); } catch (error) {
                toast(error.message, 'error');
                workspace.replaceChildren(emptyState(error.message));
            }
        });
        menu.append(button);
        return button;
    };
    const first = addMenu('Seiten', showPages);
    if (canModerate()) {
        addMenu('Gästebuch', showGuestbook);
        addMenu('Kontaktanfragen', showContact);
        addMenu('Veranstaltungshelfer', showEventHelpers);
    }
    if (canManageMembership()) addMenu('Mitgliedsanträge', showMembership);
    if (canManageUsers()) addMenu('Benutzer', showUsers);
    const logout = element('button', {className: 'text-button', text: 'Abmelden', attributes: {type: 'button'}});
    logout.addEventListener('click', async () => {
        await request('/api/auth/v1/logout', {method: 'POST'});
        csrfToken = null;
        currentRoles = [];
        toast('Du wurdest abgemeldet.', 'info');
        renderLogin();
    });

    app.replaceChildren(
        element('header', {className: 'admin-header', children: [
            element('a', {className: 'admin-brand', text: 'Waldbad · Redaktion', attributes: {href: '/admin'}}),
            element('div', {className: 'admin-account', children: [
                element('span', {text: session.user.displayName}),
                logout,
            ]}),
        ]}),
        element('main', {className: 'admin-layout', children: [
            element('aside', {className: 'admin-sidebar', children: [
                sidebarTitle,
                menu,
            ]}),
            workspace,
        ]}),
    );
    first.click();
};

const sectionHeading = (title, description) => element('header', {className: 'section-heading', children: [
    element('p', {className: 'eyebrow', text: 'Redaktion'}), element('h2', {text: title}), element('p', {text: description}),
]});

const emptyState = (text) => element('p', {className: 'empty-copy', text});

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

const moderationCard = (entry, refresh) => element('article', {className: 'management-card', children: [
    element('header', {children: [element('strong', {text: entry.displayName}), element('small', {text: entry.status + ' · ' + new Date(entry.submittedAt).toLocaleString('de-DE')})]}),
    element('p', {text: entry.message}),
    ...(entry.email ? [element('a', {text: entry.email, attributes: {href: 'mailto:' + entry.email}})] : []),
    element('div', {className: 'card-actions', children: [
        actionButton('Freigeben', `/api/admin/v1/guestbook-entries/${entry.id}/approve`, refresh, 'button', {success: 'Gästebucheintrag wurde freigegeben.'}),
        actionButton('Ablehnen', `/api/admin/v1/guestbook-entries/${entry.id}/reject`, refresh, 'secondary-button', {
            success: 'Gästebucheintrag wurde abgelehnt.',
            confirm: {title: 'Eintrag ablehnen?', description: 'Der Eintrag wird nicht im öffentlichen Gästebuch angezeigt.', label: 'Ablehnen'},
        }),
        actionButton('Spam', `/api/admin/v1/guestbook-entries/${entry.id}/mark-spam`, refresh, 'text-button danger', {
            success: 'Gästebucheintrag wurde als Spam markiert.',
            confirm: {title: 'Als Spam markieren?', description: 'Der Eintrag wird als Spam eingestuft und nicht veröffentlicht.', label: 'Als Spam markieren'},
        }),
    ]}),
]});

const contactCard = (item, refresh) => element('article', {className: 'management-card', children: [
    element('header', {children: [element('strong', {text: item.subject || 'Kontaktanfrage'}), element('small', {text: item.status + ' · ' + new Date(item.submittedAt).toLocaleString('de-DE')})]}),
    element('p', {text: item.message}),
    element('a', {text: item.name + ' · ' + item.email, attributes: {href: 'mailto:' + item.email}}),
    element('div', {className: 'card-actions', children: [
        actionButton('In Bearbeitung', `/api/admin/v1/contact-requests/${item.id}/status/in_progress`, refresh, 'secondary-button', {success: 'Kontaktanfrage ist jetzt in Bearbeitung.'}),
        actionButton('Erledigt', `/api/admin/v1/contact-requests/${item.id}/status/resolved`, refresh, 'button', {success: 'Kontaktanfrage wurde als erledigt markiert.'}),
    ]}),
]});

const userCard = (user) => element('article', {className: 'management-card', children: [
    element('header', {children: [element('strong', {text: user.displayName}), element('small', {text: user.active ? 'aktiv' : 'gesperrt'})]}),
    element('a', {text: user.email, attributes: {href: 'mailto:' + user.email}}),
    element('p', {className: 'tag-line', text: user.roles.join(' · ')}),
]});

const userCreationForm = (refresh) => {
    const roles = ['viewer', 'editor', 'publisher', 'moderator', 'admin', 'super_admin'];
    const message = formMessage();
    const form = element('form', {className: 'compact-form', children: [
        element('h3', {text: 'Benutzer anlegen'}),
        element('div', {className: 'form-grid', children: [field('Name', 'displayName'), field('E-Mail', 'email', '', 'email'), field('Initialpasswort', 'password', '', 'password')]}),
        element('div', {className: 'role-options', children: roles.map((role) => element('label', {className: 'check-field', children: [
            element('input', {attributes: {type: 'checkbox', name: 'roles', value: role}}), element('span', {text: role}),
        ]}))}),
        message,
        element('button', {className: 'button', text: 'Zugang anlegen', attributes: {type: 'submit'}}),
    ]});
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        try {
            await request('/api/admin/v1/users', {method: 'POST', body: JSON.stringify({
                displayName: data.get('displayName'), email: data.get('email'), password: data.get('password'), roles: data.getAll('roles'),
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
