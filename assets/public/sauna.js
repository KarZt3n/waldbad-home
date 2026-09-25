// Seitenerweiterung „Sauna“ (Blocktyp `extension`, Schlüssel `sauna`): Monatskalender mit freien
// und belegten Buchungseinheiten aus `GET /api/public/v1/sauna/calendar` sowie ein Anfrage-Dialog
// für freie Zeiten (`POST /api/public/v1/sauna/bookings`). Bewusst ohne Kalender-Bibliothek: Der
// Kalender zeigt nur feste Zeitraster je Tag, die als Buttons tastatur- und screenreaderbedienbar
// bleiben; auf schmalen Bildschirmen wird das Monatsraster zur Liste der Tage mit Saunazeiten.

import {element, field, formatDateDE, formatEuro, formMessage, request, toast} from '../core.js';

const WEEKDAY_LABELS = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

const toIsoDate = (date) => [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
].join('-');
const toMinutes = (time) => {
    const [hours, minutes] = time.split(':').map((part) => Number.parseInt(part, 10));

    return hours * 60 + minutes;
};
const formatDuration = (minutes) => minutes % 60 === 0
    ? `${minutes / 60} ${minutes === 60 ? 'Stunde' : 'Stunden'}`
    : `${minutes} Minuten`;
// Gleiche Rechnung wie `SaunaTerms::priceFor()`; maßgeblich bleibt der serverseitig gespeicherte Preis.
const priceFor = (terms, durationMinutes) => Math.round(terms.priceCents * durationMinutes / terms.priceUnitMinutes);
const termsSummary = (terms) => `${formatEuro(terms.priceCents)} je ${formatDuration(terms.priceUnitMinutes)} für die ganze Gruppe`
    + ` · Gruppen von ${terms.minPersons} bis ${terms.maxPersons} Personen, keine Einzelnutzung`;

/**
 * Alle Endzeiten, die sich ab `slotIndex` durch lückenlos anschließende freie Buchungseinheiten
 * desselben Tages erreichen lassen — Grundlage der „bis“-Auswahl im Anfrage-Dialog.
 */
const reachableEndTimes = (slots, slotIndex) => {
    const endTimes = [];
    for (let index = slotIndex; index < slots.length; index++) {
        const slot = slots[index];
        if (slot.state !== 'free') break;
        if (index > slotIndex && slot.startTime !== slots[index - 1].endTime) break;
        endTimes.push(slot.endTime);
    }

    return endTimes;
};

/**
 * Anfrage-Dialog für eine freie Kalender-Kachel (`selection = {day, slotIndex}`: Tag und Beginn
 * stehen fest, das Ende wird aus den anschließenden freien Einheiten gewählt) oder — ohne
 * `selection` — als „individuelle Anfrage“ mit frei wählbarem Tag und Von-bis-Zeit.
 */
const openSaunaBookingDialog = (terms, onSubmitted, selection = null) => {
    const individual = selection === null;
    const dialog = element('dialog', {className: 'event-help-dialog sauna-booking-dialog'});
    const message = formMessage();
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Sauna-Anfrage schließen'}});

    let scheduleFields;
    let schedule;
    let heading;
    if (individual) {
        const dateInput = element('input', {attributes: {type: 'date', name: 'date', id: 'sauna-booking-date', required: 'required', min: toIsoDate(new Date())}});
        const startInput = element('input', {attributes: {type: 'time', name: 'startTime', id: 'sauna-booking-start', required: 'required', step: '900'}});
        const endInput = element('input', {attributes: {type: 'time', name: 'endTime', id: 'sauna-booking-end', required: 'required', step: '900'}});
        scheduleFields = [
            element('label', {className: 'field', attributes: {for: 'sauna-booking-date'}, children: [element('span', {text: 'Wunschtag'}), dateInput]}),
            element('div', {className: 'form-grid', children: [
                element('label', {className: 'field', attributes: {for: 'sauna-booking-start'}, children: [element('span', {text: 'von'}), startInput]}),
                element('label', {className: 'field', attributes: {for: 'sauna-booking-end'}, children: [element('span', {text: 'bis'}), endInput]}),
            ]}),
        ];
        schedule = () => ({date: dateInput.value, startTime: startInput.value, endTime: endInput.value});
        heading = 'Individuelle Anfrage';
    } else {
        const {day, slotIndex} = selection;
        const slot = day.slots[slotIndex];
        const endSelect = element('select', {attributes: {name: 'endTime', id: 'sauna-booking-end'}, children: reachableEndTimes(day.slots, slotIndex)
            .map((endTime) => element('option', {text: `${endTime} Uhr`, attributes: {value: endTime}}))});
        scheduleFields = [element('div', {className: 'form-grid', children: [
            element('div', {className: 'field', children: [
                element('span', {text: 'Beginn'}),
                element('strong', {className: 'sauna-booking-start', text: `${slot.startTime} Uhr`}),
            ]}),
            element('label', {className: 'field', attributes: {for: 'sauna-booking-end'}, children: [element('span', {text: 'Ende'}), endSelect]}),
        ]})];
        schedule = () => ({date: day.date, startTime: slot.startTime, endTime: endSelect.value});
        heading = `${WEEKDAY_LABELS[day.weekday - 1]}, ${formatDateDE(day.date)}`;
    }

    const personSelect = element('select', {attributes: {name: 'personCount', id: 'sauna-booking-persons'}, children: Array
        .from({length: terms.maxPersons - terms.minPersons + 1}, (_, index) => terms.minPersons + index)
        .map((count) => element('option', {text: `${count} Personen`, attributes: {value: String(count)}}))});
    const price = element('strong', {className: 'sauna-booking-price', attributes: {'aria-live': 'polite'}});
    const updatePrice = () => {
        const {startTime, endTime} = schedule();
        const duration = startTime && endTime ? toMinutes(endTime) - toMinutes(startTime) : 0;
        price.textContent = duration > 0 ? formatEuro(priceFor(terms, duration)) : '–';
    };
    const privacy = element('input', {attributes: {name: 'privacyAccepted', type: 'checkbox', required: 'required'}});
    const submit = element('button', {className: 'button', text: 'Sauna-Anfrage absenden', attributes: {type: 'submit'}});
    const form = element('form', {className: 'public-form event-help-form', children: [
        element('header', {children: [
            element('p', {className: 'eyebrow', text: individual ? 'Sauna-Anfrage außerhalb der Kalenderzeiten' : 'Sauna-Anfrage'}),
            element('h2', {text: heading}),
            element('p', {text: `Die Sauna wird nur an Gruppen von ${terms.minPersons} bis ${terms.maxPersons} Personen vergeben; eine Einzelnutzung ist nicht möglich. Deine Anfrage ist erst verbindlich, wenn der Verein sie angenommen hat.`}),
        ]}),
        ...scheduleFields,
        element('div', {className: 'form-grid', children: [
            element('label', {className: 'field', attributes: {for: 'sauna-booking-persons'}, children: [element('span', {text: 'Personen in deiner Gruppe'}), personSelect]}),
            element('div', {className: 'field', children: [
                element('span', {text: 'Kosten für die Gruppe'}),
                price,
            ]}),
        ]}),
        element('div', {className: 'form-grid form-grid-3', children: [field('Vorname', 'firstName'), field('Nachname', 'lastName'), field('Geburtsdatum', 'birthDate', '', 'date')]}),
        field('E-Mail (optional)', 'email', '', 'email'),
        field(individual ? 'Nachricht (optional, z. B. Anlass oder Alternativtermine)' : 'Nachricht (optional)', 'message', '', 'textarea'),
        element('label', {className: 'check-field', children: [privacy, element('span', {text: 'Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung dieser Sauna-Anfrage zu.'})]}),
        message,
        submit,
    ]});
    ['firstName', 'lastName', 'birthDate'].forEach((name) => form.querySelector(`[name="${name}"]`).required = true);
    form.addEventListener('input', updatePrice);
    form.addEventListener('change', updatePrice);
    updatePrice();
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        const {date, startTime, endTime} = schedule();
        if (individual && toMinutes(endTime) <= toMinutes(startTime)) {
            message.textContent = 'Das Ende muss nach dem Beginn liegen.';
            return;
        }
        submit.disabled = true;
        try {
            const response = await request('/api/public/v1/sauna/bookings', {method: 'POST', body: JSON.stringify({
                date,
                startTime,
                endTime,
                individual,
                personCount: Number.parseInt(data.get('personCount'), 10),
                firstName: data.get('firstName'),
                lastName: data.get('lastName'),
                birthDate: data.get('birthDate'),
                email: data.get('email'),
                message: data.get('message'),
                privacyAccepted: data.get('privacyAccepted') === 'on',
            })});
            toast(response.message);
            dialog.close();
            await onSubmitted();
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
};

const SLOT_LABELS = {free: 'frei', booked: 'belegt', past: 'vorbei'};
const MONTH_LABEL = new Intl.DateTimeFormat('de-DE', {month: 'long', year: 'numeric'});

const renderSlot = (day, slot, index, terms, reload) => {
    const time = `${slot.startTime}–${slot.endTime}`;
    const label = `${WEEKDAY_LABELS[day.weekday - 1]}, ${formatDateDE(day.date)}, ${time} Uhr: ${SLOT_LABELS[slot.state] || slot.state}`;
    if (slot.state !== 'free') {
        return element('li', {className: `sauna-slot sauna-slot-${slot.state}`, attributes: {title: label}, children: [
            element('span', {className: 'sauna-slot-time', text: slot.startTime, attributes: {'aria-hidden': 'true'}}),
            element('span', {className: 'visually-hidden', text: label}),
        ]});
    }
    const button = element('button', {
        className: 'sauna-slot-button',
        text: slot.startTime,
        attributes: {type: 'button', title: `${time} Uhr – frei`, 'aria-label': `${label} – jetzt anfragen`},
    });
    button.addEventListener('click', () => openSaunaBookingDialog(terms, reload, {day, slotIndex: index}));

    return element('li', {className: 'sauna-slot sauna-slot-free', children: [button]});
};

const renderDay = (day, terms, reload, todayIso) => {
    const date = new Date(`${day.date}T00:00:00`);

    return element('div', {
        className: `sauna-month-day${day.date === todayIso ? ' is-today' : ''}${day.slots.length ? '' : ' is-empty'}`,
        attributes: {role: 'gridcell'},
        children: [
            element('p', {className: 'sauna-month-day-label', children: [
                element('span', {className: 'sauna-month-day-number', text: String(date.getDate())}),
                element('span', {className: 'sauna-month-day-weekday', text: `${WEEKDAY_LABELS[day.weekday - 1]}, ${formatDateDE(day.date)}`}),
            ]}),
            ...(day.slots.length
                ? [element('ul', {className: 'sauna-slots', children: day.slots.map((slot, index) => renderSlot(day, slot, index, terms, reload))})]
                : []),
        ],
    });
};

const renderSaunaExtension = (preview = false) => {
    const container = element('section', {className: 'sauna-extension', attributes: {'aria-label': 'Sauna-Belegung'}});
    if (preview) {
        container.append(element('p', {className: 'empty-copy', text: 'Hier erscheint im Frontend der Sauna-Belegungskalender mit Anfrageformular.'}));
        return container;
    }

    const now = new Date();
    const currentMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    let month = currentMonth;
    let initialMonthResolved = false;
    const heading = element('h3', {className: 'sauna-month-label', attributes: {'aria-live': 'polite'}});
    const previous = element('button', {className: 'secondary-button', text: '‹ Vorheriger Monat', attributes: {type: 'button'}});
    const today = element('button', {className: 'secondary-button', text: 'Aktueller Monat', attributes: {type: 'button'}});
    const next = element('button', {className: 'secondary-button', text: 'Nächster Monat ›', attributes: {type: 'button'}});
    const grid = element('div', {className: 'sauna-month'});
    const termsInfo = element('p', {className: 'sauna-terms'});
    let currentTerms = null;
    const individualRequest = element('button', {className: 'button sauna-individual-button', text: 'Individuelle Anfrage', attributes: {type: 'button', disabled: 'disabled'}});
    individualRequest.addEventListener('click', () => {
        if (currentTerms) openSaunaBookingDialog(currentTerms, load);
    });
    const individualHint = element('div', {className: 'sauna-individual', children: [
        element('p', {text: 'Keine passende Zeit im Kalender? Frag deinen Wunschtermin mit freier Uhrzeit an.'}),
        individualRequest,
    ]});
    const legend = element('p', {className: 'sauna-legend', children: [
        element('span', {className: 'sauna-legend-item sauna-legend-free', text: 'Frei – zum Anfragen anklicken'}),
        element('span', {className: 'sauna-legend-item sauna-legend-booked', text: 'Belegt'}),
    ]});

    const load = async () => {
        previous.disabled = month <= currentMonth;
        heading.textContent = MONTH_LABEL.format(month);
        const daysInMonth = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
        grid.replaceChildren(element('p', {className: 'empty-copy', text: 'Belegung wird geladen …'}));
        try {
            const calendar = await request(`/api/public/v1/sauna/calendar?from=${toIsoDate(month)}&days=${daysInMonth}`);
            const hasSeason = !!calendar.season;
            bookingArea.hidden = !hasSeason;
            noSeason.hidden = hasSeason;
            if (!hasSeason) return;
            // Beginnt die nächste Saison erst in einem späteren Monat, einmalig direkt dorthin springen.
            if (!initialMonthResolved) {
                initialMonthResolved = true;
                const seasonStart = new Date(`${calendar.season.startsOn}T00:00:00`);
                const seasonMonth = new Date(seasonStart.getFullYear(), seasonStart.getMonth(), 1);
                if (seasonMonth > month) {
                    month = seasonMonth;
                    await load();
                    return;
                }
            }
            termsInfo.textContent = termsSummary(calendar.terms);
            currentTerms = calendar.terms;
            individualRequest.disabled = false;
            if (!calendar.days.some((day) => day.slots.length > 0)) {
                grid.replaceChildren(element('p', {className: 'empty-copy', text: 'In diesem Monat sind keine Saunazeiten geplant.'}));
                return;
            }
            const leadingBlanks = (month.getDay() + 6) % 7;
            const todayIso = toIsoDate(new Date());
            grid.replaceChildren(element('div', {className: 'sauna-month-grid', attributes: {role: 'grid', 'aria-label': MONTH_LABEL.format(month)}, children: [
                ...WEEKDAY_LABELS.map((label) => element('div', {className: 'sauna-month-weekday', text: label.slice(0, 2), attributes: {role: 'columnheader', 'aria-label': label}})),
                ...Array.from({length: leadingBlanks}, () => element('div', {className: 'sauna-month-day is-outside', attributes: {'aria-hidden': 'true'}})),
                ...calendar.days.map((day) => renderDay(day, calendar.terms, load, todayIso)),
            ]}));
        } catch {
            grid.replaceChildren(element('p', {className: 'embedded-page-error', text: 'Die Sauna-Belegung konnte nicht geladen werden.'}));
        }
    };
    const showMonth = (offset) => {
        month = offset === 0 ? currentMonth : new Date(month.getFullYear(), month.getMonth() + offset, 1);
        load();
    };
    previous.addEventListener('click', () => showMonth(-1));
    today.addEventListener('click', () => showMonth(0));
    next.addEventListener('click', () => showMonth(1));

    const bookingArea = element('div', {className: 'sauna-booking-area', children: [
        termsInfo,
        individualHint,
        element('div', {className: 'sauna-toolbar', children: [today, heading, element('div', {className: 'sauna-toolbar-actions', children: [previous, next]})]}),
        grid,
        legend,
    ]});
    const noSeason = element('p', {className: 'empty-copy sauna-no-season', text: 'Aktuell keine Saison', attributes: {role: 'status'}});
    noSeason.hidden = true;

    container.append(noSeason, bookingArea);
    load();

    return container;
};

export {renderSaunaExtension};
