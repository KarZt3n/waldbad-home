// Seite „Sauna-Anfrage annehmen/ablehnen“ (siehe `PublicSaunaBookingApprovalController`) — erreichbar nur
// über die Links aus der Benachrichtigungsmail, ohne Anmeldung. Wie bei der E-Mail-Einwilligung handelt
// erst der Klick auf den Button, nicht schon das Öffnen der Seite (Link-Vorschau im E-Mail-Programm).

import {app, element, formatDateDE, formatEuro, formMessage, request} from '../core.js';
import {buildSiteFooter, buildSiteHeader} from './site-chrome.js';

const SAUNA_APPROVAL_SLUG = 'sauna-anfrage';
const STATUS_LABELS = {open: 'offen', accepted: 'bereits angenommen', rejected: 'abgelehnt', cancelled: 'storniert'};

// Der Token steht in der Adresszeile: Suchmaschinen und Referer sollen ihn nicht weitertragen.
const protectTokenInUrl = () => {
    [['robots', 'noindex, nofollow'], ['referrer', 'no-referrer']].forEach(([name, content]) => {
        const meta = document.querySelector(`meta[name="${name}"]`) || document.head.appendChild(Object.assign(document.createElement('meta'), {name}));
        meta.content = content;
    });
};

const bookingLine = (booking) => {
    const weekday = new Date(`${booking.date}T00:00:00`).toLocaleDateString('de-DE', {weekday: 'short'});

    return element('li', {className: `sauna-approval-booking status-${booking.status}`, children: [
        element('strong', {text: `${weekday}, ${formatDateDE(booking.date)} · ${booking.startTime}–${booking.endTime} Uhr`}),
        element('span', {text: `${booking.personCount} Personen · ${formatEuro(booking.priceCents)}${booking.status === 'open' ? '' : ` · ${STATUS_LABELS[booking.status] || booking.status}`}`}),
    ]});
};

const dutyChoice = (value, label, hint) => element('label', {className: 'check-field sauna-approval-choice', children: [
    element('input', {attributes: {type: 'radio', name: 'sauna-duty-required', value, ...(value === 'yes' ? {checked: 'checked'} : {})}}),
    element('span', {children: [element('strong', {text: label}), element('small', {text: hint})]}),
]});

const modeLink = (token, reject) => element('a', {
    className: 'text-button sauna-approval-switch',
    text: reject ? 'Stattdessen ablehnen' : 'Stattdessen annehmen',
    attributes: {href: `/${SAUNA_APPROVAL_SLUG}?${new URLSearchParams({token, ...(reject ? {aktion: 'ablehnen'} : {})})}`},
});

// `reject`: Link „Ablehnen“ aus der Mail (`?aktion=ablehnen`) — gleicher Token, andere Aktion.
const renderApproval = async (token, reject, content) => {
    const items = (await request('/api/public/v1/sauna-booking-approvals/preview', {method: 'POST', body: JSON.stringify({token})})).items;
    const first = items[0];
    const openCount = items.filter((booking) => booking.status === 'open').length;
    const message = formMessage();
    const actionLabel = reject ? 'ablehnen' : 'annehmen';
    const submit = element('button', {
        className: reject ? 'button danger-button' : 'button',
        text: openCount > 1 ? `${openCount} Termine ${actionLabel}` : `Anfrage ${actionLabel}`,
        attributes: {type: 'submit'},
    });
    const decision = reject
        ? [element('p', {text: 'Der Zeitraum wird im Kalender wieder frei. Die anfragende Person wird nicht automatisch benachrichtigt.'})]
        : [element('fieldset', {className: 'sauna-approval-choices', children: [
            element('legend', {text: 'Wird ein Sauna-Dienst benötigt?'}),
            dutyChoice('yes', 'Ja, Dienst wird benötigt', 'Jemand vom Verein lässt die Gäste herein, kassiert ab und schließt ab.'),
            dutyChoice('no', 'Nein, kein Dienst nötig', 'Die Gäste kommen allein herein und gehen wieder.'),
        ]})];
    const form = element('form', {className: 'public-form sauna-approval', children: [
        element('h2', {text: `Sauna-Anfrage von ${first.firstName} ${first.lastName}`}),
        element('ul', {className: 'sauna-approval-bookings', children: items.map(bookingLine)}),
        ...(first.message.trim() ? [element('p', {className: 'sauna-approval-message', text: `Nachricht: ${first.message}`})] : []),
        ...(openCount ? [
            ...decision,
            message,
            element('div', {className: 'sauna-approval-actions', children: [submit, modeLink(token, !reject)]}),
        ] : [element('p', {text: 'Für diese Anfrage gibt es keine offenen Termine mehr.'})]),
    ]});
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        try {
            const dutyRequired = form.querySelector('input[name="sauna-duty-required"]:checked')?.value !== 'no';
            const result = reject
                ? await request('/api/public/v1/sauna-booking-approvals/rejections', {method: 'POST', body: JSON.stringify({token})})
                : await request('/api/public/v1/sauna-booking-approvals', {method: 'POST', body: JSON.stringify({token, dutyRequired})});
            let detail = '';
            if (!reject) detail = dutyRequired ? ' Der Termin steht im Dienstplan und braucht noch einen Dienst.' : ' Es ist kein Sauna-Dienst nötig.';
            form.replaceChildren(
                element('h2', {text: reject ? 'Abgelehnt' : 'Angenommen'}),
                element('p', {className: 'form-message success', text: `${result.message}${detail}`}),
                element('ul', {className: 'sauna-approval-bookings', children: result.items.map(bookingLine)}),
            );
        } catch (error) {
            message.textContent = error.message;
            submit.disabled = false;
        }
    });
    content.replaceChildren(form);
};

const renderSaunaApprovalPage = async (navigationTree) => {
    protectTokenInUrl();
    const query = new URLSearchParams(window.location.search);
    const token = query.get('token');
    const reject = query.get('aktion') === 'ablehnen';
    const content = element('div', {className: 'content-card'});

    app.replaceChildren(
        buildSiteHeader(navigationTree, SAUNA_APPROVAL_SLUG, []),
        element('main', {
            className: 'page-shell',
            children: [
                element('section', {className: 'page-hero', children: [
                    element('p', {className: 'eyebrow', text: 'Vermietung · Sauna'}),
                    element('h1', {text: reject ? 'Sauna-Anfrage ablehnen' : 'Sauna-Anfrage annehmen'}),
                ]}),
                content,
            ],
        }),
        buildSiteFooter(),
    );
    if (!token) {
        content.replaceChildren(element('p', {text: 'Dieser Link ist unvollständig. Bitte nutze den vollständigen Link aus der E-Mail.'}));
        return;
    }
    content.replaceChildren(element('p', {text: 'Anfrage wird geladen …'}));
    try {
        await renderApproval(token, reject, content);
    } catch (error) {
        content.replaceChildren(element('p', {className: 'form-message', text: error.message}));
    }
};

export {SAUNA_APPROVAL_SLUG, renderSaunaApprovalPage};
