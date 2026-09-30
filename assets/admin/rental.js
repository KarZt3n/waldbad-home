// Modul „Vermietung“: aktuell mit dem Untermodul Sauna — Sauna-Anmeldungen (Status offen/angenommen/
// abgelehnt), Sauna-Saisons (Zeitraum, Dauer einer Buchungseinheit, Wochenplan Mo–So) und Kosten
// (Preis je Buchung, Gruppengröße) als Unter-Reiter unter einer gemeinsamen Route
// (`/admin/vermietung/...`).

import {confirmAction, element, field, formatDateDE, formatEuro, formMessage, request, toast} from '../core.js';
import {actionButton, emptyState, sectionHeading} from './ui.js';
import {canEditModule, hasModule} from './session.js';
import {adminPath, setAdminPath, workspace} from './shell.js';

const WEEKDAYS = [
    [1, 'Montag'], [2, 'Dienstag'], [3, 'Mittwoch'], [4, 'Donnerstag'], [5, 'Freitag'], [6, 'Samstag'], [7, 'Sonntag'],
];
const WEEKDAY_SHORT = {1: 'Mo', 2: 'Di', 3: 'Mi', 4: 'Do', 5: 'Fr', 6: 'Sa', 7: 'So'};
const BOOKING_STATUS_LABELS = {open: 'Offen', accepted: 'Angenommen', rejected: 'Abgelehnt', cancelled: 'Storniert'};
const BOOKING_STATUS_BADGES = {open: 'status-pending', accepted: 'status-active', rejected: 'status-rejected', cancelled: 'status-inactive'};

const todayIso = () => {
    const now = new Date();

    return [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
};

/** Anfragende Person: Name mit Mitgliedsnummer-Bubble, darunter Anschrift und E-Mail des verknüpften Mitglieds. */
const bookingIdentity = (booking) => {
    const contact = booking.memberContact;
    const email = booking.email || contact?.email || null;

    return element('div', {className: 'event-helper-participant-identity', children: [
        element('div', {className: 'event-helper-participant-name-row', children: [
            element('strong', {text: `${booking.firstName} ${booking.lastName}`}),
            ...(contact || booking.memberNumber
                ? [element('span', {className: 'status-badge member-link-badge is-linked', text: contact?.memberNumber || booking.memberNumber})]
                : [element('span', {className: 'status-badge member-link-badge', text: 'Kein Mitglied'})]),
        ]}),
        ...(contact ? [element('small', {className: 'event-helper-member-detail', text: `${contact.street} • ${contact.postalCode} ${contact.city}`})] : []),
        ...(email ? [element('a', {className: 'event-helper-member-detail', text: email, attributes: {href: `mailto:${email}`}})] : []),
    ]});
};

const bookingMessage = (booking) => booking.message ? [element('p', {className: 'sauna-booking-message', text: booking.message})] : [];
const bookingSubmitted = (booking) => element('small', {className: 'sauna-booking-submitted', text: `Eingegangen am ${new Date(booking.submittedAt).toLocaleString('de-DE')}`});

/**
 * „Stornieren“ mit Rückfrage; in der Rückfrage lässt sich die Stornobestätigung an die anfragende
 * Person abwählen (standardmäßig angehakt, ohne bekannte E-Mail-Adresse nicht möglich).
 */
const cancelButton = (booking, refresh) => {
    const button = element('button', {className: 'secondary-button', text: 'Stornieren', attributes: {type: 'button'}});
    button.addEventListener('click', async () => {
        const email = booking.email || booking.memberContact?.email || null;
        const notify = element('input', {attributes: {type: 'checkbox', ...(email ? {checked: 'checked'} : {disabled: 'disabled'})}});
        const confirmed = await confirmAction(
            'Sauna-Termin stornieren?',
            'Der Termin wird abgesagt und der Zeitraum im öffentlichen Kalender wieder als frei angezeigt.',
            'Stornieren',
            [element('label', {className: 'check-field', children: [
                notify,
                element('span', {text: email ? `Stornobestätigung an ${email} senden` : 'Stornobestätigung senden (keine E-Mail-Adresse bekannt)'}),
            ]})],
        );
        if (!confirmed) return;
        button.disabled = true;
        try {
            await request(`/api/admin/v1/sauna-bookings/${booking.id}/cancel`, {method: 'POST', body: JSON.stringify({notify: notify.checked})});
            toast(notify.checked ? 'Sauna-Termin wurde storniert, die Bestätigung wird versendet.' : 'Sauna-Termin wurde storniert.');
            await refresh();
        } catch (error) {
            toast(error.message, 'error');
            button.disabled = false;
        }
    });

    return button;
};

/**
 * Karte einer Sauna-Anmeldung, aufgebaut wie die Teilnehmerzeilen der Helferanfragen: links die
 * Person und der Termin, rechts Kennzeichen/Status oben und die Aktionen unten. Innerhalb der
 * Gruppe einer anfragenden Person (`inRequest`) ohne Person und Nachricht — die stehen dann im Kopf
 * der Gruppe.
 */
const bookingCard = (booking, refresh, {inRequest = false} = {}) => {
    const weekday = new Date(`${booking.date}T00:00:00`).toLocaleDateString('de-DE', {weekday: 'short'});
    const actions = canEditModule('rental_sauna') ? [
        ...(booking.status !== 'accepted' ? [actionButton('Annehmen', `/api/admin/v1/sauna-bookings/${booking.id}/accept`, refresh, 'button', {
            success: 'Sauna-Anmeldung wurde angenommen.',
        })] : []),
        // Angenommene Termine werden storniert statt abgelehnt (z. B. wenn jemand einen Tag absagt).
        ...(booking.status === 'accepted' ? [cancelButton(booking, refresh)] : []),
        ...(booking.status === 'open' ? [actionButton('Ablehnen', `/api/admin/v1/sauna-bookings/${booking.id}/reject`, refresh, 'secondary-button', {
            success: 'Sauna-Anmeldung wurde abgelehnt.',
            confirm: {
                title: 'Sauna-Anmeldung ablehnen?',
                description: 'Der Zeitraum wird im öffentlichen Kalender wieder als frei angezeigt.',
                label: 'Ablehnen',
            },
        })] : []),
    ] : [];

    return element('article', {className: `management-card sauna-booking-card status-${booking.status}${inRequest ? ' is-request-day' : ''}`, children: [
        element('div', {className: 'sauna-booking-main', children: [
            ...(inRequest ? [] : [bookingIdentity(booking)]),
            element('div', {className: 'sauna-booking-appointment', children: [
                element('strong', {text: `${weekday}, ${formatDateDE(booking.date)} · ${booking.startTime}–${booking.endTime} Uhr`}),
                element('small', {text: `${booking.personCount} Personen · ${formatEuro(booking.priceCents)}`}),
                ...(booking.participants.length ? [element('small', {className: 'sauna-booking-participants', text: `Personen: ${booking.participants.map((participant) => `${participant.firstName} ${participant.lastName}`).join(', ')}`})] : []),
                ...(inRequest ? [] : bookingMessage(booking)),
                bookingSubmitted(booking),
            ]}),
        ]}),
        element('div', {className: 'sauna-booking-side', children: [
            element('div', {className: 'sauna-booking-badges', children: [
                ...(booking.individual ? [element('span', {className: 'status-badge sauna-individual-badge', text: 'Individuelle Anfrage'})] : []),
                element('span', {className: `status-badge ${BOOKING_STATUS_BADGES[booking.status] || ''}`, text: BOOKING_STATUS_LABELS[booking.status] || booking.status}),
            ]}),
            ...(actions.length ? [element('div', {className: 'card-actions sauna-booking-actions', children: actions})] : []),
        ]}),
    ]});
};

/**
 * Alle Anmeldungen einer anfragenden Person (gleicher `requesterKey`, auch aus mehreren Anfragen)
 * als eine Gruppe: im Kopf die Person mit ihren Nachrichten und „Alle annehmen“ für alle offenen
 * Termine, darunter jeder Termin mit eigener Annahme/Ablehnung.
 */
const requestGroup = (items, refresh) => {
    const newest = items.reduce((latest, item) => (item.submittedAt > latest.submittedAt ? item : latest));
    const openCount = items.filter((item) => item.status === 'open').length;
    const requestCount = new Set(items.map((item) => item.requestId || item.id)).size;
    const messages = [...new Set(items.map((item) => item.message.trim()).filter(Boolean))];
    const acceptAll = canEditModule('rental_sauna') && openCount > 1
        ? [actionButton(`Alle annehmen (${openCount})`, `/api/admin/v1/sauna-bookings/requesters/${newest.requesterKey}/accept`, refresh, 'button', {
            success: `${openCount} Sauna-Termine wurden angenommen.`,
        })]
        : [];

    return element('article', {className: 'management-card sauna-request-group', children: [
        element('header', {className: 'sauna-request-head', children: [
            bookingIdentity(newest),
            element('div', {className: 'sauna-request-meta', children: [
                element('div', {className: 'sauna-booking-badges', children: [
                    element('span', {className: 'status-badge', text: requestCount > 1 ? `${items.length} Termine · ${requestCount} Anfragen` : `${items.length} Termine`}),
                ]}),
                ...(acceptAll.length ? [element('div', {className: 'card-actions sauna-booking-actions', children: acceptAll})] : []),
            ]}),
            ...(messages.length ? [element('div', {className: 'sauna-request-message', children: messages.map((message) => element('p', {className: 'sauna-booking-message', text: message}))})] : []),
        ]}),
        element('div', {className: 'sauna-request-days', children: items.map((item) => bookingCard(item, refresh, {inRequest: true}))}),
    ]});
};

const showSaunaBookings = async () => {
    const data = await request('/api/admin/v1/sauna-bookings');
    const today = todayIso();
    const openItems = data.items.filter((item) => item.status === 'open');
    const upcomingAccepted = data.items.filter((item) => item.status === 'accepted' && item.date >= today);
    const pastAccepted = data.items.filter((item) => item.status === 'accepted' && item.date < today).reverse();
    const rejected = data.items.filter((item) => item.status === 'rejected').reverse();
    const cancelled = data.items.filter((item) => item.status === 'cancelled').reverse();
    // Anmeldungen derselben Person (`requesterKey`, siehe `SaunaBooking::requesterKey()`) innerhalb
    // eines Abschnitts an der Stelle ihrer ersten Anmeldung zusammenfassen.
    const cards = (items) => {
        const byRequester = new Map();
        items.forEach((item) => byRequester.set(item.requesterKey, [...(byRequester.get(item.requesterKey) || []), item]));

        return items.flatMap((item) => {
            const group = byRequester.get(item.requesterKey);
            if (group.length < 2) return [bookingCard(item, showRentalManagement)];

            return group[0] === item ? [requestGroup(group, showRentalManagement)] : [];
        });
    };
    const section = (title, items, badgeClass, emptyText) => element('section', {className: 'event-helper-section', children: [
        element('div', {className: 'event-helper-section-heading', children: [
            element('h3', {text: title}),
            element('span', {className: `status-badge ${badgeClass}`, text: String(items.length)}),
        ]}),
        element('div', {className: 'card-list', children: items.length
            ? cards(items)
            : [emptyState(emptyText)]}),
    ]});
    const archive = (title, items) => element('details', {className: 'event-helper-archive', children: [
        element('summary', {children: [
            element('strong', {text: title}),
            element('span', {className: 'status-badge', text: String(items.length)}),
        ]}),
        element('div', {className: 'card-list event-helper-archive-list', children: cards(items)}),
    ]});

    workspace.replaceChildren(
        sectionHeading('Sauna-Anmeldungen', 'Anfragen aus dem öffentlichen Sauna-Kalender annehmen oder ablehnen'),
        element('div', {className: 'event-helper-groups sauna-booking-groups', children: [
            section('Offen', openItems, 'status-pending', 'Keine offenen Sauna-Anfragen.'),
            section('Angenommen – anstehend', upcomingAccepted, 'status-active', 'Keine anstehenden Sauna-Termine.'),
            ...(pastAccepted.length ? [archive('Angenommen – vergangen', pastAccepted)] : []),
            ...(rejected.length ? [archive('Abgelehnt', rejected)] : []),
            ...(cancelled.length ? [archive('Storniert', cancelled)] : []),
        ]}),
    );
};

/** Anzeige-Zustand einer Saison am heutigen Tag (siehe `SaunaSeason::covers()`). */
const seasonState = (season, today) => {
    if (season.closedOn && season.closedOn <= today) return {label: 'Abgeschlossen', badge: 'status-inactive'};
    if (season.endsOn && season.endsOn < today) return {label: 'Beendet', badge: 'status-inactive'};
    if (season.startsOn > today) return {label: season.closedOn ? 'Geplant, abgeschlossen' : 'Geplant', badge: 'status-pending'};
    if (season.closedOn) return {label: `Aktiv bis ${formatDateDE(season.closedOn)}`, badge: 'status-active'};

    return {label: 'Aktiv', badge: 'status-active'};
};

/** Saisons haben keine eigene Bezeichnung; sie werden über ihren Zeitraum benannt. */
const seasonLabel = (season) => season.endsOn
    ? `Saison ${formatDateDE(season.startsOn)} – ${formatDateDE(season.endsOn)}`
    : `Saison ab ${formatDateDE(season.startsOn)}`;

const weeklySummary = (openingHours) => WEEKDAYS
    .map(([weekday]) => {
        const windows = openingHours
            .filter((hours) => hours.weekday === weekday)
            .sort((first, second) => first.startTime.localeCompare(second.startTime));

        return windows.length ? `${WEEKDAY_SHORT[weekday]} ${windows.map((hours) => `${hours.startTime}–${hours.endTime}`).join(', ')}` : null;
    })
    .filter(Boolean)
    .join(' · ');

/**
 * Wochenplan-Editor: je Wochentag beliebig viele Von-bis-Zeitfenster. `openingHours` wird in place
 * gepflegt und beim Speichern unverändert als Nutzlast übernommen.
 */
const buildWeeklyPlanEditor = (openingHours) => {
    const container = element('div', {className: 'sauna-plan-editor'});
    const render = () => {
        container.replaceChildren(...WEEKDAYS.map(([weekday, label]) => {
            const windows = openingHours.filter((hours) => hours.weekday === weekday);
            const add = element('button', {className: 'secondary-button button-compact', text: '＋ Zeitfenster', attributes: {type: 'button'}});
            add.addEventListener('click', () => {
                const previous = windows[windows.length - 1];
                openingHours.push({weekday, startTime: previous ? previous.endTime : '16:00', endTime: previous ? previous.endTime : '20:00'});
                render();
            });

            return element('fieldset', {className: 'sauna-plan-day', children: [
                element('legend', {text: label}),
                ...(windows.length ? windows.map((hours) => {
                    const start = element('input', {attributes: {type: 'time', 'aria-label': `${label}: Beginn`, required: 'required'}});
                    const end = element('input', {attributes: {type: 'time', 'aria-label': `${label}: Ende`, required: 'required'}});
                    start.value = hours.startTime;
                    end.value = hours.endTime;
                    start.addEventListener('change', () => hours.startTime = start.value);
                    end.addEventListener('change', () => hours.endTime = end.value);
                    const remove = element('button', {className: 'text-button danger', text: 'Entfernen', attributes: {type: 'button', 'aria-label': `${label}: Zeitfenster entfernen`}});
                    remove.addEventListener('click', () => {
                        openingHours.splice(openingHours.indexOf(hours), 1);
                        render();
                    });

                    return element('div', {className: 'sauna-plan-window', children: [start, element('span', {text: 'bis', attributes: {'aria-hidden': 'true'}}), end, remove]});
                }) : [element('small', {className: 'sauna-plan-closed', text: 'Keine Saunazeiten'})]),
                add,
            ]});
        }));
    };
    render();

    return container;
};

const closureLabel = (closure) => {
    const range = closure.endsOn && closure.endsOn !== closure.startsOn
        ? `${formatDateDE(closure.startsOn)} – ${formatDateDE(closure.endsOn)}`
        : formatDateDE(closure.startsOn);

    return closure.reason ? `${range} (${closure.reason})` : range;
};

/**
 * Schließzeiten-Editor: Tage bzw. Zeiträume, an denen die Sauna trotz Wochenplan geschlossen ist.
 * `closures` wird wie beim Wochenplan in place gepflegt; ohne „bis“ gilt eine Schließzeit nur für
 * den einen Tag.
 */
const buildClosuresEditor = (closures, idPrefix) => {
    const container = element('div', {className: 'sauna-closure-editor'});
    const render = () => {
        const add = element('button', {className: 'secondary-button button-compact', text: '＋ Schließzeit', attributes: {type: 'button'}});
        add.addEventListener('click', () => {
            closures.push({startsOn: '', endsOn: '', reason: ''});
            render();
            container.querySelector('.sauna-closure-row:last-of-type input')?.focus();
        });
        container.replaceChildren(
            ...(closures.length ? closures.map((closure, index) => {
                const id = `${idPrefix}-closure-${index}`;
                const startsOn = element('input', {attributes: {type: 'date', id: `${id}-from`, required: 'required'}});
                const endsOn = element('input', {attributes: {type: 'date', id: `${id}-to`}});
                const reason = element('input', {attributes: {type: 'text', id: `${id}-reason`, maxlength: '120', placeholder: 'z. B. Revision'}});
                startsOn.value = closure.startsOn;
                endsOn.value = closure.endsOn || '';
                reason.value = closure.reason || '';
                startsOn.addEventListener('change', () => {
                    closure.startsOn = startsOn.value;
                    endsOn.min = startsOn.value;
                });
                endsOn.min = closure.startsOn;
                endsOn.addEventListener('change', () => closure.endsOn = endsOn.value);
                reason.addEventListener('input', () => closure.reason = reason.value);
                const remove = element('button', {className: 'text-button danger', text: 'Entfernen', attributes: {type: 'button', 'aria-label': 'Schließzeit entfernen'}});
                remove.addEventListener('click', () => {
                    closures.splice(closures.indexOf(closure), 1);
                    render();
                });

                return element('div', {className: 'sauna-closure-row', children: [
                    element('label', {className: 'field', attributes: {for: `${id}-from`}, children: [element('span', {text: 'Von'}), startsOn]}),
                    element('label', {className: 'field', attributes: {for: `${id}-to`}, children: [element('span', {text: 'Bis (optional)'}), endsOn]}),
                    element('label', {className: 'field', attributes: {for: `${id}-reason`}, children: [element('span', {text: 'Grund (optional)'}), reason]}),
                    remove,
                ]});
            }) : [element('small', {className: 'sauna-plan-closed', text: 'Keine Schließzeiten'})]),
            add,
        );
    };
    render();

    return container;
};

/** Aufklappbarer Abschnitt im Saison-Dialog; `summary` fasst den Inhalt im zugeklappten Zustand zusammen. */
const seasonSection = (title, summary, content, open = false) => {
    const details = element('details', {className: 'event-helper-archive sauna-season-section', children: [
        element('summary', {children: [
            element('strong', {text: title}),
            element('small', {className: 'sauna-season-section-summary', text: summary}),
        ]}),
        element('div', {className: 'sauna-season-section-body', children: [content]}),
    ]});
    details.open = open;

    return details;
};

const openSeasonDialog = (season, onSaved) => {
    const dialog = element('dialog', {className: 'activity-dialog sauna-season-dialog'});
    const key = season?.id || 'new';
    const startsOn = field('Saisonbeginn', `sauna-season-starts-${key}`, season?.startsOn || '', 'date');
    const endsOn = field('Saisonende (optional)', `sauna-season-ends-${key}`, season?.endsOn || '', 'date');
    const slotDuration = field('Dauer einer Buchungseinheit (Minuten)', `sauna-season-slot-${key}`, season?.slotDurationMinutes ?? 60, 'number');
    const slotDurationInput = slotDuration.querySelector('input');
    slotDurationInput.min = '15';
    slotDurationInput.max = '720';
    slotDurationInput.step = '15';
    const openingHours = (season?.openingHours || []).map((hours) => ({...hours}));
    const closures = (season?.closures || []).map((closure) => ({...closure}));
    const message = formMessage();
    const submit = element('button', {className: 'button', text: season ? 'Änderungen speichern' : 'Saison anlegen', attributes: {type: 'submit'}});
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
    const actions = [cancel, submit];
    if (season && !season.closedOn) {
        const closeButton = element('button', {className: 'secondary-button', text: 'Saison abschließen', attributes: {type: 'button'}});
        closeButton.addEventListener('click', async () => {
            const confirmed = await confirmAction(
                `${seasonLabel(season)} abschließen?`,
                'Ab heute ist die Sauna in dieser Saison nicht mehr buchbar. Das geplante Enddatum bleibt unverändert, bestehende Anmeldungen bleiben erhalten.',
                'Saison abschließen',
            );
            if (!confirmed) return;
            try {
                await request(`/api/admin/v1/sauna-seasons/${season.id}/close`, {method: 'POST'});
                toast('Saison wurde abgeschlossen.');
                dialog.close();
                await onSaved();
            } catch (error) {
                toast(error.message, 'error');
            }
        });
        actions.unshift(closeButton);
    }
    if (season?.closedOn) {
        const reopenButton = element('button', {className: 'secondary-button', text: 'Saison wieder eröffnen', attributes: {type: 'button'}});
        reopenButton.addEventListener('click', async () => {
            const confirmed = await confirmAction(
                `${seasonLabel(season)} wieder eröffnen?`,
                'Der Abschluss wird aufgehoben, die Sauna ist in dieser Saison wieder buchbar (bis zum geplanten Enddatum, sofern gesetzt). Es darf nur eine Saison gleichzeitig offen sein.',
                'Wieder eröffnen',
            );
            if (!confirmed) return;
            try {
                await request(`/api/admin/v1/sauna-seasons/${season.id}/reopen`, {method: 'POST'});
                toast('Saison wurde wieder eröffnet.');
                dialog.close();
                await onSaved();
            } catch (error) {
                toast(error.message, 'error');
            }
        });
        actions.unshift(reopenButton);
    }
    if (season) {
        const deleteButton = element('button', {className: 'button danger-button', text: 'Löschen', attributes: {type: 'button'}});
        deleteButton.addEventListener('click', async () => {
            const confirmed = await confirmAction(
                `${seasonLabel(season)} löschen?`,
                'Die Saison und ihr Wochenplan werden entfernt. Bestehende Sauna-Anmeldungen bleiben erhalten.',
                'Löschen',
            );
            if (!confirmed) return;
            try {
                await request(`/api/admin/v1/sauna-seasons/${season.id}`, {method: 'DELETE'});
                toast('Saison wurde gelöscht.');
                dialog.close();
                await onSaved();
            } catch (error) {
                toast(error.message, 'error');
            }
        });
        actions.unshift(deleteButton);
    }
    const form = element('form', {className: 'activity-dialog-content', children: [
        element('header', {children: [
            element('p', {className: 'eyebrow', text: season ? 'Saison bearbeiten' : 'Neue Saison'}),
            element('h2', {text: season ? seasonLabel(season) : 'Sauna-Saison anlegen'}),
        ]}),
        element('div', {className: 'form-grid', children: [startsOn, endsOn]}),
        element('small', {text: 'Ohne Saisonende ist die Sauna ab Saisonbeginn bis auf Weiteres buchbar.'}),
        ...(season?.closedOn ? [element('p', {className: 'sauna-season-closed-note', text: `Abgeschlossen zum ${formatDateDE(season.closedOn)} – ab diesem Tag nicht mehr buchbar.`})] : []),
        ...(season ? [] : [element('small', {text: 'Es ist immer nur eine Saison aktiv: Eine noch offene Saison wird automatisch zum Beginn dieser neuen Saison abgeschlossen (frühestens heute), ihr Enddatum bleibt unverändert.'})]),
        slotDuration,
        element('small', {text: 'Die Zeitfenster werden im Kalender in Einheiten dieser Länge aufgeteilt. Gäste können mehrere aufeinanderfolgende Einheiten anfragen.'}),
        seasonSection('Wochenplan', weeklySummary(openingHours) || 'Noch keine Zeiten', buildWeeklyPlanEditor(openingHours), true),
        seasonSection(
            'Schließzeiten',
            closures.length ? `${closures.length} ${closures.length === 1 ? 'Schließzeit' : 'Schließzeiten'}` : 'Keine',
            element('div', {className: 'sauna-season-section-content', children: [
                element('small', {text: 'An diesen Tagen ist die Sauna trotz Wochenplan geschlossen: Der Kalender zeigt „Geschlossen“, Anfragen für diese Tage werden abgewiesen.'}),
                buildClosuresEditor(closures, `sauna-season-${key}`),
            ]}),
            closures.length > 0,
        ),
        message,
        element('div', {className: 'confirm-dialog-actions', children: actions}),
    ]});
    startsOn.querySelector('input').required = true;
    slotDurationInput.required = true;
    cancel.addEventListener('click', () => dialog.close());
    close.addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        try {
            await request(season ? `/api/admin/v1/sauna-seasons/${season.id}` : '/api/admin/v1/sauna-seasons', {
                method: season ? 'PUT' : 'POST',
                body: JSON.stringify({
                    startsOn: startsOn.querySelector('input').value,
                    endsOn: endsOn.querySelector('input').value || null,
                    slotDurationMinutes: Number.parseInt(slotDurationInput.value, 10),
                    openingHours,
                    closures: closures.map((closure) => ({startsOn: closure.startsOn, endsOn: closure.endsOn || null, reason: closure.reason.trim()})),
                }),
            });
            toast(season ? 'Saison wurde gespeichert.' : 'Saison wurde angelegt.');
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

const showSaunaSeasons = async () => {
    const data = await request('/api/admin/v1/sauna-seasons');
    const heading = sectionHeading('Sauna-Saison', 'Saisonzeitraum und buchbare Zeiten je Wochentag festlegen');
    if (canEditModule('rental_sauna')) {
        const create = element('button', {className: 'button', text: '＋ Neue Saison', attributes: {type: 'button'}});
        create.addEventListener('click', () => openSeasonDialog(null, showRentalManagement));
        heading.append(create);
    }
    const today = todayIso();
    const rows = data.items.map((season) => {
        const state = seasonState(season, today);
        const row = element('button', {
            className: 'activity-list-row',
            attributes: {type: 'button', ...(canEditModule('rental_sauna') ? {} : {disabled: 'disabled'})},
            children: [
                element('span', {className: 'activity-list-copy', children: [
                    element('strong', {text: seasonLabel(season)}),
                    element('small', {text: `${season.endsOn ? '' : 'ohne Ende · '}`
                        + `${season.closedOn ? `abgeschlossen zum ${formatDateDE(season.closedOn)} · ` : ''}Einheiten à ${season.slotDurationMinutes} Min.`}),
                    element('small', {text: weeklySummary(season.openingHours)}),
                    ...(season.closures.length ? [element('small', {text: `Geschlossen: ${season.closures.map(closureLabel).join(', ')}`})] : []),
                ]}),
                element('span', {className: `status-badge ${state.badge}`, text: state.label}),
                ...(canEditModule('rental_sauna') ? [element('span', {className: 'activity-list-edit', text: 'Bearbeiten ›'})] : []),
            ],
        });
        if (canEditModule('rental_sauna')) row.addEventListener('click', () => openSeasonDialog(season, showRentalManagement));

        return row;
    });

    workspace.replaceChildren(
        heading,
        element('div', {className: 'activity-list', children: rows.length ? rows : [emptyState('Noch keine Sauna-Saison angelegt.')]}),
    );
};

const showSaunaTerms = async () => {
    const terms = await request('/api/admin/v1/sauna-terms');
    const editable = canEditModule('rental_sauna');
    const price = field('Preis je Buchung (€)', 'sauna-terms-price', (terms.priceCents / 100).toFixed(2), 'number');
    const priceInput = price.querySelector('input');
    priceInput.min = '0';
    priceInput.max = '1000';
    priceInput.step = '0.01';
    const unit = field('für eine Dauer von (Minuten)', 'sauna-terms-unit', terms.priceUnitMinutes, 'number');
    const unitInput = unit.querySelector('input');
    unitInput.min = '15';
    unitInput.max = '1440';
    unitInput.step = '15';
    const minPersons = field('Mindestens Personen', 'sauna-terms-min', terms.minPersons, 'number');
    const minInput = minPersons.querySelector('input');
    minInput.min = '2';
    minInput.max = '50';
    const maxPersons = field('Höchstens Personen', 'sauna-terms-max', terms.maxPersons, 'number');
    const maxInput = maxPersons.querySelector('input');
    maxInput.min = '2';
    maxInput.max = '50';
    [priceInput, unitInput, minInput, maxInput].forEach((input) => {
        input.required = true;
        input.disabled = !editable;
    });
    const example = element('p', {className: 'sauna-terms-example', attributes: {'aria-live': 'polite'}});
    const updateExample = () => {
        const cents = Math.round(Number.parseFloat(priceInput.value || '0') * 100);
        const minutes = Number.parseInt(unitInput.value || '0', 10);
        example.textContent = minutes > 0
            ? `Beispiel: Eine Gruppe mit ${minInput.value || '?'}–${maxInput.value || '?'} Personen zahlt ${formatEuro(cents)} für ${minutes} Minuten; `
                + `kürzere oder längere Buchungen werden anteilig berechnet (1 Stunde = ${formatEuro(Math.round(cents * 60 / minutes))}).`
            : '';
    };
    [priceInput, unitInput, minInput, maxInput].forEach((input) => input.addEventListener('input', updateExample));
    updateExample();
    const message = formMessage();
    const submit = element('button', {className: 'button', text: 'Kosten speichern', attributes: {type: 'submit'}});
    const form = element('form', {className: 'management-card sauna-terms-form', children: [
        element('fieldset', {className: 'sauna-terms-group', children: [
            element('legend', {text: 'Kosten'}),
            element('div', {className: 'form-grid', children: [price, unit]}),
            element('small', {text: 'Der Preis gilt für die ganze Gruppe, unabhängig von der Personenzahl.'}),
        ]}),
        element('fieldset', {className: 'sauna-terms-group', children: [
            element('legend', {text: 'Gruppengröße'}),
            element('div', {className: 'form-grid', children: [minPersons, maxPersons]}),
            element('small', {text: 'Eine Einzelnutzung ist ausgeschlossen – die Mindestgröße beträgt immer mindestens zwei Personen.'}),
        ]}),
        example,
        message,
        ...(editable ? [submit] : []),
    ]});
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        try {
            await request('/api/admin/v1/sauna-terms', {method: 'PUT', body: JSON.stringify({
                priceCents: Math.round(Number.parseFloat(priceInput.value) * 100),
                priceUnitMinutes: Number.parseInt(unitInput.value, 10),
                minPersons: Number.parseInt(minInput.value, 10),
                maxPersons: Number.parseInt(maxInput.value, 10),
            })});
            toast('Kosten wurden gespeichert. Sie gelten für neue Sauna-Anfragen.');
            message.textContent = '';
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        } finally {
            submit.disabled = false;
        }
    });

    workspace.replaceChildren(
        sectionHeading('Sauna-Kosten', 'Preis je Buchung und zulässige Gruppengröße festlegen'),
        form,
    );
};

/**
 * Untermodule der Vermietung (erste Reiterebene) mit ihren Bereichen (zweite Reiterebene), z. B.
 * `/admin/vermietung/sauna/anmeldungen`. Weitere Mietobjekte werden hier als eigener Eintrag mit
 * eigenem CMS-Modul ergänzt.
 */
const RENTAL_SUBMODULES = [
    {slug: 'sauna', label: 'Sauna', module: 'rental_sauna', sections: [
        {slug: 'anmeldungen', label: 'Anmeldungen', show: () => showSaunaBookings()},
        {slug: 'saison', label: 'Saison', show: () => showSaunaSeasons()},
        {slug: 'kosten', label: 'Kosten', show: () => showSaunaTerms()},
    ]},
];
let activeSubmoduleSlug = null;
const activeSectionSlugs = {};

const tabStrip = (label, items, activeSlug, pathFor, onSelect) => element('nav', {
    className: 'sub-tab-strip',
    attributes: {'aria-label': label},
    children: items.map((item) => {
        const link = element('a', {
            className: `sub-tab${item.slug === activeSlug ? ' active' : ''}`,
            text: item.label,
            attributes: {href: adminPath(...pathFor(item)), ...(item.slug === activeSlug ? {'aria-current': 'page'} : {})},
        });
        link.addEventListener('click', async (event) => {
            event.preventDefault();
            setAdminPath(pathFor(item));
            await onSelect(item);
        });

        return link;
    }),
});

const showRentalManagement = async (segments = []) => {
    const submodules = RENTAL_SUBMODULES.filter((submodule) => hasModule(submodule.module));
    if (segments[0] === 'vermietung' && submodules.some((submodule) => submodule.slug === segments[1])) {
        activeSubmoduleSlug = segments[1];
        if (segments[2]) activeSectionSlugs[segments[1]] = segments[2];
    }
    const submodule = submodules.find((candidate) => candidate.slug === activeSubmoduleSlug) || submodules[0];
    if (!submodule) {
        workspace.replaceChildren(emptyState('Für diesen Zugang ist kein Bereich der Vermietung freigeschaltet.'));
        return;
    }
    activeSubmoduleSlug = submodule.slug;
    const section = submodule.sections.find((candidate) => candidate.slug === activeSectionSlugs[submodule.slug]) || submodule.sections[0];
    activeSectionSlugs[submodule.slug] = section.slug;
    setAdminPath(['vermietung', submodule.slug, section.slug], true);

    const submoduleStrip = tabStrip(
        'Vermietung',
        submodules,
        submodule.slug,
        (item) => ['vermietung', item.slug, activeSectionSlugs[item.slug] || item.sections[0].slug],
        async (item) => {
            activeSubmoduleSlug = item.slug;
            await showRentalManagement();
        },
    );
    const sectionStrip = tabStrip(
        submodule.label,
        submodule.sections,
        section.slug,
        (item) => ['vermietung', submodule.slug, item.slug],
        async (item) => {
            activeSectionSlugs[submodule.slug] = item.slug;
            await showRentalManagement();
        },
    );
    sectionStrip.classList.add('sub-tab-strip-nested');

    // Wie in `showEventManagement`: der Bereich schreibt in `workspace`, danach wird sein Inhalt
    // zusammen mit beiden Reiterleisten in ein Tab-Panel umgehängt.
    await section.show();
    const panel = element('div', {className: 'sub-tab-panel', children: [...workspace.children]});
    workspace.replaceChildren(submoduleStrip, sectionStrip, panel);
};

export {showRentalManagement};
