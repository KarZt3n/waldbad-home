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
const formatLeadDays = (days) => (days === 1 ? '1 Tag' : `${days} Tagen`);
const termsSummary = (terms) => `${formatEuro(terms.priceCents)} je Buchung von ${formatDuration(terms.priceUnitMinutes)} für die ganze Gruppe`
    + ` · Gruppen von ${terms.minPersons} bis ${terms.maxPersons} Personen, keine Einzelnutzung`
    + (terms.leadDays > 0 ? ` · Anfragen mit mindestens ${formatLeadDays(terms.leadDays)} Vorlauf` : '');

/**
 * Endzeit einer Buchung ab `slotIndex`: Jede Buchung dauert genau `terms.priceUnitMinutes` und
 * muss sich durch lückenlos anschließende freie Buchungseinheiten desselben Tages erreichen lassen.
 * `null`, wenn ab hier keine Buchung dieser Dauer möglich ist.
 */
const bookingEndTime = (slots, slotIndex, terms) => {
    const end = toMinutes(slots[slotIndex].startTime) + terms.priceUnitMinutes;
    for (let index = slotIndex; index < slots.length; index++) {
        const slot = slots[index];
        if (slot.state !== 'free') return null;
        if (index > slotIndex && slot.startTime !== slots[index - 1].endTime) return null;
        if (toMinutes(slot.endTime) === end) return slot.endTime;
        if (toMinutes(slot.endTime) > end) return null;
    }

    return null;
};

/**
 * Weitere Personen der Gruppe — wie „Ich bringe jemanden mit“ bei der Helferanmeldung, aber nur mit
 * Vor- und Nachname. Die anfragende Person (aus `guest`) zählt als Person 1; für die übrigen
 * Personen bis zur Mindestgröße (`terms.minPersons`) stehen von Anfang an feste Karten im Formular,
 * weitere lassen sich bis `terms.maxPersons` hinzufügen und wieder entfernen. `onChange` meldet jede
 * Änderung der Personenzahl.
 */
const createCompanionsEditor = (terms, guest, onChange) => {
    const minCompanions = terms.minPersons - 1;
    const maxCompanions = terms.maxPersons - 1;
    const companions = element('div', {className: 'event-help-companions'});
    const add = element('button', {className: 'secondary-button event-help-add-companion', text: '＋ Ich bringe jemanden mit', attributes: {type: 'button'}});
    const refresh = () => {
        [...companions.children].forEach((card, index) => {
            card.querySelector('.event-help-companion-title').textContent = `Person ${index + 2}`;
            card.querySelector('.event-help-companion-remove').hidden = index < minCompanions;
        });
        add.hidden = companions.children.length >= maxCompanions;
    };
    const addCard = () => {
        const id = `sauna-companion-${Math.random().toString(36).slice(2)}`;
        const companionField = (label, key) => {
            const wrapper = field(label, `${id}-${key}`);
            const input = wrapper.querySelector('input');
            input.dataset.companionField = key;
            input.maxLength = 120;
            input.required = true;
            return wrapper;
        };
        const remove = element('button', {className: 'text-button danger event-help-companion-remove', text: 'Entfernen', attributes: {type: 'button'}});
        const card = element('fieldset', {className: 'event-help-companion', children: [
            element('div', {className: 'event-help-companion-heading', children: [
                element('legend', {className: 'event-help-companion-title'}),
                remove,
            ]}),
            element('div', {className: 'form-grid', children: [companionField('Vorname', 'firstName'), companionField('Nachname', 'lastName')]}),
        ]});
        remove.addEventListener('click', () => {
            card.remove();
            refresh();
            onChange();
        });
        companions.append(card);

        return card;
    };
    for (let index = 0; index < minCompanions; index++) addCard();
    add.addEventListener('click', () => {
        if (companions.children.length >= maxCompanions) return;
        addCard();
        refresh();
        onChange();
    });
    refresh();

    return {
        elements: [companions, add],
        count: () => companions.children.length + 1,
        participants: () => [
            {
                firstName: guest[0].querySelector('[name="firstName"]').value,
                lastName: guest[0].querySelector('[name="lastName"]').value,
            },
            ...[...companions.children].map((card) => ({
                firstName: card.querySelector('[data-companion-field="firstName"]').value,
                lastName: card.querySelector('[data-companion-field="lastName"]').value,
            })),
        ],
    };
};

// Längste wöchentliche Wiederholung (siehe `SubmitWeeklySaunaBookingUseCase::MAX_WEEKS`).
const MAX_REPEAT_WEEKS = 52;
const addDays = (isoDate, days) => {
    const date = new Date(`${isoDate}T00:00:00`);
    date.setDate(date.getDate() + days);

    return toIsoDate(date);
};
const repeatDates = (firstIso, untilIso) => {
    const dates = [];
    for (let date = addDays(firstIso, 7); date <= untilIso; date = addDays(date, 7)) dates.push(date);

    return dates;
};
// Der Kalender liefert höchstens 42 Tage je Abfrage (siehe `GetSaunaCalendarQuery::MAX_DAYS`).
const CALENDAR_CHUNK_DAYS = 42;
const loadCalendarDays = async (fromIso, toIso) => {
    const days = new Map();
    for (let from = fromIso; from <= toIso; from = addDays(from, CALENDAR_CHUNK_DAYS)) {
        const span = Math.min(CALENDAR_CHUNK_DAYS, Math.round((new Date(`${toIso}T00:00:00`) - new Date(`${from}T00:00:00`)) / 86_400_000) + 1);
        const calendar = await request(`/api/public/v1/sauna/calendar?from=${from}&days=${span}`);
        calendar.days.forEach((day) => days.set(day.date, day));
    }

    return days;
};
/** Stand eines Wiederholungstermins laut Kalender; nur freie Termine werden angefragt (wie im Backend). */
const repeatDateState = (day, startTime, endTime) => {
    if (!day) return {free: false, text: 'keine Saunazeit'};
    if (day.closed) return {free: false, text: day.closedReason ? `geschlossen (${day.closedReason})` : 'geschlossen'};
    const units = day.slots.filter((slot) => slot.startTime >= startTime && slot.endTime <= endTime);
    const covered = units.length > 0 && units[0].startTime === startTime && units[units.length - 1].endTime === endTime
        && units.every((slot, index) => index === 0 || units[index - 1].endTime === slot.startTime);
    if (!covered) return {free: false, text: 'keine Saunazeit'};
    if (units.some((slot) => slot.state !== 'free')) return {free: false, text: 'belegt'};

    return {free: true, text: 'frei'};
};

/**
 * „Wöchentlich wiederholen“ im Anfrage-Dialog: bis zu einem Enddatum frühestens eine Woche nach dem
 * ersten Termin, höchstens bis Saisonende bzw. `MAX_REPEAT_WEEKS` Wochen. Nach Wahl des Enddatums
 * listet es jeden Termin der Serie mit seinem Stand laut Kalender auf. `null`, wenn die Saison keine
 * Wiederholung mehr zulässt.
 */
const createWeeklyRepeat = (day, startTime, endTime, season, onChange) => {
    const min = addDays(day.date, 7);
    const max = [addDays(day.date, 7 * MAX_REPEAT_WEEKS), season?.endsOn].filter(Boolean).sort()[0];
    if (max < min) return null;

    const toggle = element('input', {attributes: {type: 'checkbox', name: 'repeatWeekly'}});
    const untilField = field('Wiederholen bis', 'repeatWeeklyUntil', '', 'date');
    const until = untilField.querySelector('input');
    Object.assign(until, {min, max});
    const dateList = element('div', {className: 'sauna-repeat-dates', attributes: {'aria-live': 'polite'}});
    const details = element('div', {className: 'sauna-repeat-details', children: [
        untilField,
        element('p', {className: 'field-hint', text: `Jeden ${WEEKDAY_LABELS[day.weekday - 1]} von ${startTime} bis ${endTime} Uhr. Belegte oder geschlossene Termine werden übersprungen; jeder Termin wird vom Verein bestätigt.`}),
        dateList,
    ]});
    details.hidden = true;

    const selectedUntil = () => toggle.checked && until.value >= min && until.value <= max ? until.value : null;
    // Stand je Wiederholungstermin (Datum → {free, text}); `null`, solange er noch geladen wird.
    let states = null;
    let loading = 0;
    const dateLabel = (iso) => `${WEEKDAY_LABELS[(new Date(`${iso}T00:00:00`).getDay() + 6) % 7].slice(0, 2)}, ${formatDateDE(iso)}`;
    const renderDates = () => {
        const value = selectedUntil();
        if (!value) {
            dateList.replaceChildren();
            return;
        }
        const dates = repeatDates(day.date, value);
        dateList.replaceChildren(
            element('p', {className: 'sauna-repeat-dates-title', text: states ? 'Diese Termine werden angefragt:' : 'Termine werden geprüft …'}),
            element('ul', {children: [
                element('li', {className: 'is-free', text: `${dateLabel(day.date)} · erster Termin`}),
                ...dates.map((date) => {
                    const state = states?.get(date);
                    if (!state) return element('li', {text: dateLabel(date)});

                    return element('li', {
                        className: state.free ? 'is-free' : 'is-skipped',
                        text: `${dateLabel(date)} · ${state.free ? 'frei' : `${state.text} – wird übersprungen`}`,
                    });
                }),
            ]}),
        );
    };
    const refreshDates = async () => {
        states = null;
        renderDates();
        onChange();
        const value = selectedUntil();
        if (!value) return;
        const ticket = ++loading;
        try {
            const days = await loadCalendarDays(min, value);
            if (ticket !== loading) return;
            states = new Map(repeatDates(day.date, value).map((date) => [date, repeatDateState(days.get(date), startTime, endTime)]));
        } catch {
            if (ticket !== loading) return;
            dateList.replaceChildren(element('p', {className: 'field-hint', text: 'Die Termine konnten nicht geprüft werden; belegte Termine werden beim Absenden übersprungen.'}));
            return;
        }
        renderDates();
        onChange();
    };
    toggle.addEventListener('change', () => {
        details.hidden = !toggle.checked;
        until.required = toggle.checked;
        refreshDates();
    });
    until.addEventListener('change', refreshDates);

    return {
        elements: [
            element('label', {className: 'check-field', children: [toggle, element('span', {text: 'Wöchentlich wiederholen'})]}),
            details,
        ],
        until: selectedUntil,
        // Anzahl der Termine, die voraussichtlich angefragt werden; `exact`, sobald der Kalender geprüft ist.
        count: () => {
            const value = selectedUntil();
            if (!value) return {dates: 1, exact: true};
            const dates = repeatDates(day.date, value);

            return states
                ? {dates: 1 + dates.filter((date) => states.get(date)?.free).length, exact: true}
                : {dates: 1 + dates.length, exact: false};
        },
    };
};

/**
 * Anfrage-Dialog für eine freie Kalender-Kachel (`selection = {day, slotIndex}`) mit anfragender
 * Person, Personen mit Namen und Nachricht: Tag und Beginn stehen fest, das Ende wird aus den anschließenden freien
 * Einheiten gewählt. Optional wiederholt sich der Termin wöchentlich bis zu einem gewählten Tag.
 */
const openSaunaBookingDialog = (terms, onSubmitted, selection, season) => {
    const dialog = element('dialog', {className: 'event-help-dialog sauna-booking-dialog'});
    const message = formMessage();
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Sauna-Anfrage schließen'}});

    const {day, slotIndex} = selection;
    const slot = day.slots[slotIndex];
    const when = `${WEEKDAY_LABELS[day.weekday - 1]}, ${formatDateDE(day.date)}`;
    const guest = guestFields();
    const endTime = bookingEndTime(day.slots, slotIndex, terms);
    const summaryText = element('strong');
    const price = priceFor(terms, terms.priceUnitMinutes);
    const summaryPrice = element('span', {className: 'sauna-wish-price'});
    const editor = createCompanionsEditor(terms, guest, () => updateSummary());
    const repeat = createWeeklyRepeat(day, slot.startTime, endTime, season, () => updateSummary());
    const updateSummary = () => {
        const until = repeat?.until();
        const {dates, exact} = repeat?.count() ?? {dates: 1, exact: true};
        summaryText.textContent = `Wunschtag: ${when.slice(0, 2)}, ${formatDateDE(day.date)} · ${slot.startTime}–${endTime} Uhr · ${editor.count()} Personen`
            + (until ? ` · wöchentlich bis ${formatDateDE(until)} (${exact ? '' : 'bis zu '}${dates} ${dates === 1 ? 'Termin' : 'Termine'})` : '');
        summaryPrice.textContent = until ? `${dates} × ${formatEuro(price)}` : formatEuro(price);
    };
    const privacy = element('input', {attributes: {name: 'privacyAccepted', type: 'checkbox', required: 'required'}});
    const submit = element('button', {className: 'button', text: 'Sauna-Anfrage absenden', attributes: {type: 'submit'}});
    const form = element('form', {className: 'public-form event-help-form', children: [
        element('header', {children: [
            element('p', {className: 'eyebrow', text: 'Sauna-Anfrage'}),
            element('h2', {text: when}),
            element('p', {text: groupHint(terms)}),
        ]}),
        ...guest,
        ...editor.elements,
        element('div', {className: 'sauna-wishes', children: [
            element('h3', {text: 'Wunschtag'}),
            element('p', {className: 'field-hint', text: 'Der Wunschtag wird für dich reserviert und vom Verein bestätigt.'}),
            element('div', {className: 'sauna-wish', children: [
                element('div', {className: 'sauna-wish-head', children: [summaryText, summaryPrice]}),
                element('div', {className: 'sauna-wish-body', children: [
                    element('div', {className: 'field', children: [element('span', {text: 'Wunschtag'}), element('strong', {className: 'sauna-booking-start', text: when})]}),
                    element('div', {className: 'form-grid', children: [
                        element('div', {className: 'field', children: [element('span', {text: 'von'}), element('strong', {className: 'sauna-booking-start', text: `${slot.startTime} Uhr`})]}),
                        element('div', {className: 'field', children: [element('span', {text: 'bis'}), element('strong', {className: 'sauna-booking-start', text: `${endTime} Uhr`})]}),
                    ]}),
                    ...(repeat?.elements ?? []),
                ]}),
            ]}),
        ]}),
        privacyField(privacy),
        message,
        submit,
    ]});
    requireGuestFields(form);
    form.addEventListener('input', updateSummary);
    form.addEventListener('change', updateSummary);
    updateSummary();
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        await submitRequest(submit, message, dialog, onSubmitted, {
            date: day.date,
            startTime: slot.startTime,
            endTime,
            personCount: editor.count(),
            participants: editor.participants(),
            ...(repeat?.until() ? {repeatWeeklyUntil: repeat.until()} : {}),
            ...guestPayload(data),
        });
    });
    showDialog(dialog, close, form);
};

const groupHint = (terms) => `Die Sauna wird nur an Gruppen von ${terms.minPersons} bis ${terms.maxPersons} Personen vergeben; eine Einzelnutzung ist nicht möglich. Deine Anfrage ist erst verbindlich, wenn der Verein sie angenommen hat.`;
const guestFields = () => [
    element('fieldset', {className: 'event-help-companion', children: [
        element('div', {className: 'event-help-companion-heading', children: [
            element('legend', {className: 'event-help-companion-title', text: 'Person 1'}),
        ]}),
        element('div', {className: 'form-grid', children: [field('Vorname', 'firstName'), field('Nachname', 'lastName')]}),
    ]}),
];
const requireGuestFields = (form) => ['firstName', 'lastName'].forEach((name) => { form.querySelector(`[name="${name}"]`).required = true; });
const guestPayload = (data) => ({
    firstName: data.get('firstName'),
    lastName: data.get('lastName'),
    privacyAccepted: data.get('privacyAccepted') === 'on',
});
const privacyField = (privacy) => element('label', {className: 'check-field', children: [privacy, element('span', {text: 'Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung dieser Sauna-Anfrage zu.'})]});
const submitRequest = async (submit, message, dialog, onSubmitted, payload, onError = () => {}) => {
    submit.disabled = true;
    try {
        const response = await request('/api/public/v1/sauna/bookings', {method: 'POST', body: JSON.stringify(payload)});
        // Bei übersprungenen Terminen einer wöchentlichen Serie nennt die Meldung die Daten – länger lesbar halten.
        toast(response.message, 'success', response.skippedDates?.length ? 12000 : undefined);
        dialog.close();
        await onSubmitted();
    } catch (error) {
        message.textContent = error.message;
        toast(error.message, 'error');
        onError(error);
        submit.disabled = false;
    }
};
const showDialog = (dialog, close, form) => {
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(close, form);
    document.body.append(dialog);
    dialog.showModal();
};

const SLOT_LABELS = {free: 'frei', booked: 'belegt', past: 'vorbei', too_soon: 'zu kurzfristig'};
const MONTH_LABEL = new Intl.DateTimeFormat('de-DE', {month: 'long', year: 'numeric'});

const renderSlot = (day, slot, index, terms, reload, season) => {
    const time = `${slot.startTime}–${slot.endTime}`;
    const label = `${WEEKDAY_LABELS[day.weekday - 1]}, ${formatDateDE(day.date)}, ${time} Uhr: ${SLOT_LABELS[slot.state] || slot.state}`;
    // Ab dieser Einheit ist keine Buchung der festen Dauer möglich: nicht anklickbar.
    if (slot.state === 'free' && bookingEndTime(day.slots, index, terms) === null) {
        const shortLabel = `${WEEKDAY_LABELS[day.weekday - 1]}, ${formatDateDE(day.date)}, ${time} Uhr: frei, aber kein Platz für eine Buchung von ${formatDuration(terms.priceUnitMinutes)}`;
        return element('li', {className: 'sauna-slot sauna-slot-short', attributes: {title: shortLabel}, children: [
            element('span', {className: 'sauna-slot-time', text: slot.startTime, attributes: {'aria-hidden': 'true'}}),
            element('span', {className: 'visually-hidden', text: shortLabel}),
        ]});
    }
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
    button.addEventListener('click', () => openSaunaBookingDialog(terms, reload, {day, slotIndex: index}, season));

    return element('li', {className: 'sauna-slot sauna-slot-free', children: [button]});
};

const renderDay = (day, terms, reload, todayIso, season) => {
    const date = new Date(`${day.date}T00:00:00`);

    return element('div', {
        className: `sauna-month-day${day.date === todayIso ? ' is-today' : ''}${day.closed ? ' is-closed' : (day.slots.length ? '' : ' is-empty')}`,
        attributes: {role: 'gridcell'},
        children: [
            element('p', {className: 'sauna-month-day-label', children: [
                element('span', {className: 'sauna-month-day-number', text: String(date.getDate())}),
                element('span', {className: 'sauna-month-day-weekday', text: `${WEEKDAY_LABELS[day.weekday - 1]}, ${formatDateDE(day.date)}`}),
            ]}),
            ...(day.closed ? [element('p', {className: 'sauna-day-closed', text: day.closedReason ? `Geschlossen · ${day.closedReason}` : 'Geschlossen'})] : []),
            ...(day.slots.length
                ? [element('ul', {className: 'sauna-slots', children: day.slots.map((slot, index) => renderSlot(day, slot, index, terms, reload, season))})]
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
    const legend = element('p', {className: 'sauna-legend', children: [
        element('span', {className: 'sauna-legend-item sauna-legend-free', text: 'Frei – zum Anfragen anklicken'}),
        element('span', {className: 'sauna-legend-item sauna-legend-booked', text: 'Belegt'}),
        element('span', {className: 'sauna-legend-item sauna-legend-too-soon', text: 'Zu kurzfristig – nur mit Vorlauf anfragbar'}),
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
            if (!calendar.days.some((day) => day.slots.length > 0)) {
                grid.replaceChildren(element('p', {className: 'empty-copy', text: 'In diesem Monat sind keine Saunazeiten geplant.'}));
                return;
            }
            const leadingBlanks = (month.getDay() + 6) % 7;
            const todayIso = toIsoDate(new Date());
            grid.replaceChildren(element('div', {className: 'sauna-month-grid', attributes: {role: 'grid', 'aria-label': MONTH_LABEL.format(month)}, children: [
                ...WEEKDAY_LABELS.map((label) => element('div', {className: 'sauna-month-weekday', text: label.slice(0, 2), attributes: {role: 'columnheader', 'aria-label': label}})),
                ...Array.from({length: leadingBlanks}, () => element('div', {className: 'sauna-month-day is-outside', attributes: {'aria-hidden': 'true'}})),
                ...calendar.days.map((day) => renderDay(day, calendar.terms, load, todayIso, calendar.season)),
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
