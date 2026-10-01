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
    + ` · Mindestdauer ${formatDuration(terms.priceUnitMinutes)}`
    + ` · Gruppen von ${terms.minPersons} bis ${terms.maxPersons} Personen, keine Einzelnutzung`;

/**
 * Alle Endzeiten, die sich ab `slotIndex` durch lückenlos anschließende freie Buchungseinheiten
 * desselben Tages erreichen lassen und mindestens die Mindestdauer (`terms.priceUnitMinutes`)
 * umfassen — Grundlage der „bis“-Auswahl im Anfrage-Dialog. Leer, wenn ab hier keine Anfrage der
 * Mindestdauer möglich ist.
 */
const reachableEndTimes = (slots, slotIndex, terms) => {
    const endTimes = [];
    const earliestEnd = toMinutes(slots[slotIndex].startTime) + terms.priceUnitMinutes;
    for (let index = slotIndex; index < slots.length; index++) {
        const slot = slots[index];
        if (slot.state !== 'free') break;
        if (index > slotIndex && slot.startTime !== slots[index - 1].endTime) break;
        if (toMinutes(slot.endTime) >= earliestEnd) endTimes.push(slot.endTime);
    }

    return endTimes;
};

/**
 * Anfrage-Dialog für eine freie Kalender-Kachel (`selection = {day, slotIndex}`): Tag und Beginn
 * stehen fest, das Ende wird aus den anschließenden freien Einheiten gewählt.
 */
const openSaunaBookingDialog = (terms, onSubmitted, selection) => {
    const dialog = element('dialog', {className: 'event-help-dialog sauna-booking-dialog'});
    const message = formMessage();
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Sauna-Anfrage schließen'}});

    const {day, slotIndex} = selection;
    const slot = day.slots[slotIndex];
    const endSelect = element('select', {attributes: {name: 'endTime', id: 'sauna-booking-end'}, children: reachableEndTimes(day.slots, slotIndex, terms)
        .map((endTime) => element('option', {text: `${endTime} Uhr`, attributes: {value: endTime}}))});
    const personSelect = personCountSelect(terms, 'personCount', 'sauna-booking-persons');
    const price = element('strong', {className: 'sauna-booking-price', attributes: {'aria-live': 'polite'}});
    const updatePrice = () => {
        const duration = toMinutes(endSelect.value) - toMinutes(slot.startTime);
        price.textContent = duration > 0 ? formatEuro(priceFor(terms, duration)) : '–';
    };
    const privacy = element('input', {attributes: {name: 'privacyAccepted', type: 'checkbox', required: 'required'}});
    const submit = element('button', {className: 'button', text: 'Sauna-Anfrage absenden', attributes: {type: 'submit'}});
    const form = element('form', {className: 'public-form event-help-form', children: [
        element('header', {children: [
            element('p', {className: 'eyebrow', text: 'Sauna-Anfrage'}),
            element('h2', {text: `${WEEKDAY_LABELS[day.weekday - 1]}, ${formatDateDE(day.date)}`}),
            element('p', {text: groupHint(terms)}),
        ]}),
        element('div', {className: 'form-grid', children: [
            element('div', {className: 'field', children: [
                element('span', {text: 'Beginn'}),
                element('strong', {className: 'sauna-booking-start', text: `${slot.startTime} Uhr`}),
            ]}),
            element('label', {className: 'field', attributes: {for: 'sauna-booking-end'}, children: [element('span', {text: 'Ende'}), endSelect]}),
        ]}),
        element('div', {className: 'form-grid', children: [
            element('label', {className: 'field', attributes: {for: 'sauna-booking-persons'}, children: [element('span', {text: 'Personen in deiner Gruppe'}), personSelect]}),
            element('div', {className: 'field', children: [element('span', {text: 'Kosten für die Gruppe'}), price]}),
        ]}),
        ...guestFields(),
        field('Nachricht (optional)', 'message', '', 'textarea'),
        privacyField(privacy),
        message,
        submit,
    ]});
    requireGuestFields(form);
    form.addEventListener('change', updatePrice);
    updatePrice();
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        await submitRequest(submit, message, dialog, onSubmitted, {
            date: day.date,
            startTime: slot.startTime,
            endTime: endSelect.value,
            personCount: Number.parseInt(personSelect.value, 10),
            ...guestPayload(data),
        });
    });
    showDialog(dialog, close, form);
};

/**
 * Individuelle Anfrage: zuerst die anfragende Person, dann beliebig viele Wunschtage (je Tag eine
 * eigene Reservierung). Personenzahl und Namen aller Personen gelten entweder einmal für alle Tage
 * („Kommt ihr an allen Tagen als dieselbe Gruppe?“) oder werden je Wunschtag angegeben. Die
 * anfragende Person ist immer Person 1.
 */
const openIndividualSaunaRequestDialog = (terms, season, onSubmitted) => {
    const dialog = element('dialog', {className: 'event-help-dialog sauna-booking-dialog'});
    const message = formMessage();
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Sauna-Anfrage schließen'}});
    const guest = guestFields();
    const bookerName = () => {
        const first = guest[0].querySelector('[name="firstName"]').value.trim();
        const last = guest[0].querySelector('[name="lastName"]').value.trim();

        return first || last ? `${first} ${last}`.trim() : 'du';
    };

    let idCounter = 0;
    const nextId = (prefix) => `${prefix}-${++idCounter}`;
    const participantsEditor = () => {
        const countId = nextId('sauna-persons');
        const count = personCountSelect(terms, null, countId);
        const list = element('ol', {className: 'sauna-participants'});
        let rows = [];
        // Ausgeblendete Abschnitte (Gruppe vs. je Wunschtag) dürfen keine Pflichtfelder enthalten.
        let required = true;
        const renderRows = () => {
            const wanted = Number.parseInt(count.value, 10) - 1;
            while (rows.length < wanted) {
                const rowId = nextId('sauna-participant');
                const firstName = element('input', {attributes: {type: 'text', id: `${rowId}-first`, maxlength: '120', autocomplete: 'off'}});
                const lastName = element('input', {attributes: {type: 'text', id: `${rowId}-last`, maxlength: '120', autocomplete: 'off'}});
                firstName.required = required;
                lastName.required = required;
                rows.push({firstName, lastName, element: element('li', {children: [element('div', {className: 'form-grid', children: [
                    element('label', {className: 'field', attributes: {for: `${rowId}-first`}, children: [element('span', {text: 'Vorname'}), firstName]}),
                    element('label', {className: 'field', attributes: {for: `${rowId}-last`}, children: [element('span', {text: 'Nachname'}), lastName]}),
                ]})]})});
            }
            rows = rows.slice(0, wanted);
            const booker = element('li', {className: 'sauna-participant-booker', children: [
                element('span', {className: 'sauna-participant-booker-name', text: bookerName()}),
                element('small', {text: ' (anfragende Person)'}),
            ]});
            list.replaceChildren(booker, ...rows.map((row) => row.element));
        };
        count.addEventListener('change', renderRows);
        renderRows();

        return {
            element: element('div', {className: 'sauna-participants-editor', children: [
                element('label', {className: 'field', attributes: {for: countId}, children: [element('span', {text: 'Personen in der Gruppe'}), count]}),
                element('p', {className: 'field-hint', text: 'Bitte die Namen aller Personen angeben.'}),
                list,
            ]}),
            count: () => Number.parseInt(count.value, 10),
            refreshBooker: () => { list.querySelector('.sauna-participant-booker-name').textContent = bookerName(); },
            participants: () => [
                {
                    firstName: guest[0].querySelector('[name="firstName"]').value,
                    lastName: guest[0].querySelector('[name="lastName"]').value,
                },
                ...rows.map((row) => ({firstName: row.firstName.value, lastName: row.lastName.value})),
            ],
            setRequired: (value) => {
                required = value;
                rows.forEach((row) => {
                    row.firstName.required = value;
                    row.lastName.required = value;
                });
            },
        };
    };

    const sameGroupYes = element('input', {attributes: {type: 'radio', name: 'sameGroup', value: 'yes', checked: 'checked'}});
    const sameGroupNo = element('input', {attributes: {type: 'radio', name: 'sameGroup', value: 'no'}});
    const sameGroup = () => sameGroupYes.checked;
    const groupEditor = participantsEditor();
    const groupSection = element('div', {className: 'sauna-group', children: [groupEditor.element]});

    let days = [];
    const dayList = element('div', {className: 'sauna-wish-list'});
    const addDayButton = element('button', {className: 'secondary-button sauna-wish-add', text: '＋ Wunschtag hinzufügen', attributes: {type: 'button'}});
    const createDay = () => {
        const dayId = nextId('sauna-day');
        // Nur Tage der Saison sind anfragbar (serverseitig ebenso geprüft).
        const today = toIsoDate(new Date());
        const dateInput = element('input', {attributes: {
            type: 'date',
            id: `${dayId}-date`,
            required: 'required',
            min: season.startsOn > today ? season.startsOn : today,
            ...(season.endsOn ? {max: season.endsOn} : {}),
        }});
        // Nur volle Stunden. „von“ nur so spät, dass bis 23 Uhr noch die Mindestdauer bleibt; „bis“
        // erst ab Beginn + Mindestdauer (aufgerundet auf die volle Stunde) und damit vorausgewählt.
        const startInput = timeSelect(`${dayId}-start`, 'von');
        const endInput = timeSelect(`${dayId}-end`, 'bis');
        startInput.setRange({from: 0, to: LAST_HOUR_OF_DAY - terms.priceUnitMinutes}, null);
        endInput.setRange(null, null);
        startInput.onChange(() => {
            const earliestEnd = startInput.value ? toMinutes(startInput.value) + terms.priceUnitMinutes : null;
            endInput.setRange(earliestEnd === null ? null : {from: earliestEnd, to: LAST_HOUR_OF_DAY}, earliestEnd);
        });
        const editor = participantsEditor();
        const personsSection = element('div', {className: 'sauna-day-persons', children: [editor.element]});
        const summaryText = element('strong');
        const summaryPrice = element('span', {className: 'sauna-wish-price'});
        const remove = element('button', {className: 'text-button danger sauna-wish-remove', text: 'Wunschtag entfernen', attributes: {type: 'button'}});
        const details = element('details', {className: 'sauna-wish', children: [
            element('summary', {children: [summaryText, summaryPrice]}),
            element('div', {className: 'sauna-wish-body', children: [
                element('label', {className: 'field', attributes: {for: `${dayId}-date`}, children: [element('span', {text: 'Wunschtag'}), dateInput]}),
                element('div', {className: 'form-grid', children: [
                    element('label', {className: 'field', attributes: {for: `${dayId}-start`}, children: [element('span', {text: 'von'}), startInput.element]}),
                    element('label', {className: 'field', attributes: {for: `${dayId}-end`}, children: [element('span', {text: 'bis'}), endInput.element]}),
                ]}),
                personsSection,
                remove,
            ]}),
        ]});
        const entry = {details, dateInput, startInput, endInput, editor, personsSection, summaryText, summaryPrice, remove};
        remove.addEventListener('click', () => {
            days = days.filter((candidate) => candidate !== entry);
            renderDays();
        });

        return entry;
    };
    const personCountFor = (entry) => (sameGroup() ? groupEditor : entry.editor).count();
    const updateSummaries = () => {
        groupEditor.refreshBooker();
        days.forEach((entry, index) => {
            entry.editor.refreshBooker();
            const {dateInput, startInput, endInput} = entry;
            const weekday = dateInput.value ? WEEKDAY_LABELS[(new Date(`${dateInput.value}T00:00:00`).getDay() + 6) % 7].slice(0, 2) : '';
            const when = dateInput.value ? `${weekday}, ${formatDateDE(dateInput.value)}` : 'noch kein Tag gewählt';
            const time = startInput.value && endInput.value ? ` · ${startInput.value}–${endInput.value} Uhr` : '';
            const duration = startInput.value && endInput.value ? toMinutes(endInput.value) - toMinutes(startInput.value) : 0;
            entry.summaryText.textContent = `Wunschtag ${index + 1}: ${when}${time} · ${personCountFor(entry)} Personen`;
            entry.summaryPrice.textContent = duration > 0 ? formatEuro(priceFor(terms, duration)) : '';
            entry.remove.hidden = days.length === 1;
        });
    };
    const updateGroupMode = () => {
        groupSection.hidden = !sameGroup();
        groupEditor.setRequired(sameGroup());
        days.forEach((entry) => {
            entry.personsSection.hidden = sameGroup();
            entry.editor.setRequired(!sameGroup());
        });
        updateSummaries();
    };
    function renderDays() {
        dayList.replaceChildren(...days.map((entry) => entry.details));
        updateGroupMode();
    }
    addDayButton.addEventListener('click', () => {
        days.forEach((entry) => { entry.details.open = false; });
        const entry = createDay();
        entry.details.open = true;
        days.push(entry);
        renderDays();
        entry.dateInput.focus();
    });
    const first = createDay();
    first.details.open = true;
    days.push(first);

    const privacy = element('input', {attributes: {name: 'privacyAccepted', type: 'checkbox', required: 'required'}});
    const submit = element('button', {className: 'button', text: 'Sauna-Anfrage absenden', attributes: {type: 'submit'}});
    const form = element('form', {className: 'public-form event-help-form', children: [
        element('header', {children: [
            element('p', {className: 'eyebrow', text: 'Sauna-Anfrage'}),
            element('h2', {text: 'Individuelle Anfrage'}),
            element('p', {text: groupHint(terms)}),
        ]}),
        ...guest,
        element('fieldset', {className: 'radio-group sauna-same-group', children: [
            element('legend', {text: 'Kommt ihr an allen Wunschtagen als dieselbe Gruppe?'}),
            element('label', {className: 'radio-field', children: [sameGroupYes, element('span', {text: 'Ja, Personen einmal angeben'})]}),
            element('label', {className: 'radio-field', children: [sameGroupNo, element('span', {text: 'Nein, Personen je Wunschtag angeben'})]}),
        ]}),
        groupSection,
        element('div', {className: 'sauna-wishes', children: [
            element('h3', {text: 'Wunschtage'}),
            element('p', {className: 'field-hint', text: 'Jeder Wunschtag wird für dich reserviert und vom Verein bestätigt.'}),
            dayList,
            addDayButton,
        ]}),
        field('Nachricht (optional, z. B. Anlass)', 'message', '', 'textarea'),
        privacyField(privacy),
        message,
        submit,
    ]});
    requireGuestFields(form);
    renderDays();
    [sameGroupYes, sameGroupNo].forEach((radio) => radio.addEventListener('change', updateGroupMode));
    form.addEventListener('input', updateSummaries);
    form.addEventListener('change', updateSummaries);
    // Pflichtfelder in einem zugeklappten Wunschtag: aufklappen, damit der Browser das Feld zeigen kann.
    form.addEventListener('invalid', (event) => { event.target.closest('details')?.setAttribute('open', ''); }, true);
    const markDays = (indexes) => days.forEach((entry, index) => {
        const marked = indexes.includes(index);
        entry.details.classList.toggle('is-invalid', marked);
        if (marked) {
            entry.details.open = true;
        }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        markDays([]);
        const invalidDay = days.findIndex(({startInput, endInput}) => toMinutes(endInput.value) <= toMinutes(startInput.value));
        if (invalidDay >= 0) {
            markDays([invalidDay]);
            message.textContent = `Wunschtag ${invalidDay + 1}: Das Ende muss nach dem Beginn liegen.`;
            return;
        }
        const data = new FormData(form);
        await submitRequest(submit, message, dialog, onSubmitted, {
            individual: true,
            days: days.map((entry) => {
                const editor = sameGroup() ? groupEditor : entry.editor;

                return {
                    date: entry.dateInput.value,
                    startTime: entry.startInput.value,
                    endTime: entry.endInput.value,
                    personCount: editor.count(),
                    participants: editor.participants(),
                };
            }),
            ...guestPayload(data),
        }, (error) => markDays(error.details?.days || []));
    });
    showDialog(dialog, close, form);
};

const groupHint = (terms) => `Die Sauna wird nur an Gruppen von ${terms.minPersons} bis ${terms.maxPersons} Personen vergeben; eine Einzelnutzung ist nicht möglich. Deine Anfrage ist erst verbindlich, wenn der Verein sie angenommen hat.`;
const personCountSelect = (terms, name, id) => element('select', {attributes: {...(name ? {name} : {}), id}, children: Array
    .from({length: terms.maxPersons - terms.minPersons + 1}, (_, index) => terms.minPersons + index)
    .map((count) => element('option', {text: `${count} Personen`, attributes: {value: String(count)}}))});
const LAST_HOUR_OF_DAY = 23 * 60;

/**
 * Uhrzeit für individuelle Anfragen als Auswahl voller Stunden (`HH:00`); `value` ist leer, solange
 * nichts gewählt ist. `setRange()` beschränkt die wählbaren Stunden (z. B. „bis“ erst ab Beginn +
 * Mindestdauer, aufgerundet auf die volle Stunde) und wählt `preselect`, wenn die bisherige Wahl
 * außerhalb liegt; ohne Bereich (`null`) ist die Auswahl gesperrt.
 */
const timeSelect = (id, label) => {
    const hour = element('select', {attributes: {id, required: 'required', 'aria-label': label}});
    const select = (range, minutes) => {
        const options = [];
        for (let value = Math.ceil(range.from / 60) * 60; value <= range.to; value += 60) {
            const time = `${String(value / 60).padStart(2, '0')}:00`;
            options.push(element('option', {text: `${time} Uhr`, attributes: {value: time}}));
        }
        hour.replaceChildren(element('option', {text: '--:--', attributes: {value: ''}}), ...options);
        hour.value = minutes === null ? '' : `${String(Math.floor(minutes / 60)).padStart(2, '0')}:00`;
    };
    select({from: 0, to: LAST_HOUR_OF_DAY}, null);

    return {
        element: hour,
        get value() { return hour.value; },
        onChange: (callback) => hour.addEventListener('change', callback),
        setRange: (range, preselect) => {
            hour.disabled = range === null || Math.ceil(range.from / 60) * 60 > range.to;
            if (hour.disabled) {
                select({from: 0, to: LAST_HOUR_OF_DAY}, null);
                return;
            }
            const current = hour.value ? toMinutes(hour.value) : null;
            const earliest = Math.ceil(range.from / 60) * 60;
            select(range, current !== null && current >= earliest && current <= range.to ? current : (preselect === null ? null : Math.ceil(preselect / 60) * 60));
        },
    };
};
const guestFields = () => [
    element('div', {className: 'form-grid form-grid-3', children: [field('Vorname', 'firstName'), field('Nachname', 'lastName'), field('Geburtsdatum', 'birthDate', '', 'date')]}),
    field('E-Mail (optional)', 'email', '', 'email'),
];
const requireGuestFields = (form) => ['firstName', 'lastName', 'birthDate'].forEach((name) => { form.querySelector(`[name="${name}"]`).required = true; });
const guestPayload = (data) => ({
    firstName: data.get('firstName'),
    lastName: data.get('lastName'),
    birthDate: data.get('birthDate'),
    email: data.get('email'),
    message: data.get('message'),
    privacyAccepted: data.get('privacyAccepted') === 'on',
});
const privacyField = (privacy) => element('label', {className: 'check-field', children: [privacy, element('span', {text: 'Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung dieser Sauna-Anfrage zu.'})]});
const submitRequest = async (submit, message, dialog, onSubmitted, payload, onError = () => {}) => {
    submit.disabled = true;
    try {
        const response = await request('/api/public/v1/sauna/bookings', {method: 'POST', body: JSON.stringify(payload)});
        toast(response.message);
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

const SLOT_LABELS = {free: 'frei', booked: 'belegt', past: 'vorbei'};
const MONTH_LABEL = new Intl.DateTimeFormat('de-DE', {month: 'long', year: 'numeric'});

const renderSlot = (day, slot, index, terms, reload) => {
    const time = `${slot.startTime}–${slot.endTime}`;
    const label = `${WEEKDAY_LABELS[day.weekday - 1]}, ${formatDateDE(day.date)}, ${time} Uhr: ${SLOT_LABELS[slot.state] || slot.state}`;
    // Ab dieser Einheit reicht die freie Zeit nicht für die Mindestdauer: nicht anklickbar.
    if (slot.state === 'free' && reachableEndTimes(day.slots, index, terms).length === 0) {
        const shortLabel = `${WEEKDAY_LABELS[day.weekday - 1]}, ${formatDateDE(day.date)}, ${time} Uhr: frei, aber kürzer als die Mindestdauer von ${formatDuration(terms.priceUnitMinutes)}`;
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
    button.addEventListener('click', () => openSaunaBookingDialog(terms, reload, {day, slotIndex: index}));

    return element('li', {className: 'sauna-slot sauna-slot-free', children: [button]});
};

const renderDay = (day, terms, reload, todayIso) => {
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
    let currentSeason = null;
    const individualRequest = element('button', {className: 'button sauna-individual-button', text: 'Individuelle Anfrage', attributes: {type: 'button', disabled: 'disabled'}});
    individualRequest.addEventListener('click', () => {
        if (currentTerms && currentSeason) openIndividualSaunaRequestDialog(currentTerms, currentSeason, load);
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
            currentSeason = calendar.season;
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
