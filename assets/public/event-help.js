// Helferanmeldung zu einer Veranstaltung („Ich möchte helfen“). Wird erst beim Klick auf den Button
// nachgeladen (siehe `renderPublicBlock` in `content-rendering.js`).

import {element, field, formMessage, request, toast} from '../core.js';

const openEventHelpDialog = async (block) => {
    const availability = await request(`/api/public/v1/event-activities/${encodeURIComponent(block.eventIdentifier)}`);
    const dialog = element('dialog', {className: 'event-help-dialog'});
    const message = formMessage();
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Helferanmeldung schließen'}});
    const privacy = element('input', {attributes: {name: 'privacyAccepted', type: 'checkbox', required: 'required'}});
    const activityDetails = (activity, helperStatus = null) => {
        const scheduleParts = [];
        if (activity.time) scheduleParts.push(`Start: ${activity.time}`);
        if (activity.meetTime) scheduleParts.push(`Ende: ${activity.meetTime}`);
        if (activity.meetPlace) scheduleParts.push(`Treffpunkt: ${activity.meetPlace}`);
        return element('span', {children: [
            element('strong', {text: activity.name}),
            ...(helperStatus ? [element('small', {text: helperStatus})] : []),
            ...(activity.description ? [element('small', {text: activity.description})] : []),
            ...(scheduleParts.length ? [element('small', {className: 'event-activity-schedule', text: scheduleParts.join(' · ')})] : []),
            ...(activity.remark ? [element('small', {className: 'event-activity-remark', text: activity.remark})] : []),
        ]});
    };
    // Nur auswählbare Aktivitäten erscheinen als Checkbox mit Helferzahl, alle übrigen als reine Auflistung.
    const activityInputs = [];
    const activityEntries = (availability.items || []).map((activity) => {
        if (activity.selectable === false) {
            // Unsichtbare Platzhalter-Checkbox (ohne Name, nicht fokussierbar), damit der Text exakt so weit
            // eingerückt ist wie bei auswählbaren Aktivitäten – unabhängig von der Checkbox-Größe des Browsers.
            const spacer = element('input', {className: 'event-activity-choice-spacer', attributes: {
                type: 'checkbox', disabled: 'disabled', tabindex: '-1', 'aria-hidden': 'true',
            }});
            return element('div', {className: 'event-activity-choice is-listed', children: [spacer, activityDetails(activity)]});
        }
        const isFull = activity.registeredHelpers >= activity.requiredHelpers;
        const input = element('input', {attributes: {
            type: 'checkbox', name: 'activityIds', value: activity.id,
            ...(isFull ? {disabled: 'disabled'} : {}),
        }});
        input.checked = activity.preselected === true && !isFull;
        activityInputs.push(input);
        return element('label', {className: `event-activity-choice${isFull ? ' is-full' : ''}`, children: [
            input,
            activityDetails(activity, isFull
                ? `Belegt · ${activity.registeredHelpers} von ${activity.requiredHelpers} Helfern angemeldet`
                : `${activity.registeredHelpers} von ${activity.requiredHelpers} Helfern angemeldet`),
        ]});
    });
    const selectableActivityInputs = activityInputs.filter((input) => !input.disabled);
    const updateActivityRequirement = () => {
        const hasSelection = selectableActivityInputs.some((input) => input.checked);
        selectableActivityInputs.forEach((input, index) => input.required = !hasSelection && index === 0);
    };
    selectableActivityInputs.forEach((input) => input.addEventListener('change', updateActivityRequirement));
    updateActivityRequirement();
    const allActivitiesFull = activityInputs.length > 0 && selectableActivityInputs.length === 0;
    const submitButton = element('button', {
        className: 'button',
        text: allActivitiesFull ? 'Aktuell keine Plätze frei' : 'Helferanmeldung absenden',
        attributes: {type: 'submit', ...(allActivitiesFull ? {disabled: 'disabled'} : {})},
    });
    // „Ich bringe jemanden mit“: Jede weitere Person wird als eigene Helferanmeldung mit denselben Aktivitäten gespeichert.
    const maxCompanions = 8;
    const companions = element('div', {className: 'event-help-companions'});
    const addCompanion = element('button', {className: 'secondary-button event-help-add-companion', text: '＋ Ich bringe jemanden mit', attributes: {type: 'button'}});
    const refreshCompanions = () => {
        [...companions.children].forEach((card, index) => {
            card.querySelector('.event-help-companion-title').textContent = `Person ${index + 2}`;
        });
        addCompanion.disabled = companions.children.length >= maxCompanions;
    };
    addCompanion.addEventListener('click', () => {
        if (companions.children.length >= maxCompanions) return;
        const id = `event-help-companion-${Math.random().toString(36).slice(2)}`;
        const companionField = (label, key, type = 'text') => {
            const wrapper = field(label, `${id}-${key}`, '', type);
            const input = wrapper.querySelector('input');
            input.dataset.companionField = key;
            input.required = true;
            return wrapper;
        };
        const remove = element('button', {className: 'text-button danger', text: 'Entfernen', attributes: {type: 'button'}});
        const card = element('fieldset', {className: 'event-help-companion', children: [
            element('div', {className: 'event-help-companion-heading', children: [
                element('legend', {className: 'event-help-companion-title', text: 'Weitere Person'}),
                remove,
            ]}),
            element('div', {className: 'form-grid form-grid-3', children: [
                companionField('Vorname', 'firstName'),
                companionField('Nachname', 'lastName'),
                companionField('Geburtsdatum', 'birthDate', 'date'),
            ]}),
        ]});
        remove.addEventListener('click', () => {
            card.remove();
            refreshCompanions();
        });
        companions.append(card);
        refreshCompanions();
    });
    const companionPayload = () => [...companions.children].map((card) => ({
        firstName: card.querySelector('[data-companion-field="firstName"]').value,
        lastName: card.querySelector('[data-companion-field="lastName"]').value,
        birthDate: card.querySelector('[data-companion-field="birthDate"]').value,
    }));
    const form = element('form', {className: 'public-form event-help-form', children: [
        element('header', {children: [
            element('p', {className: 'eyebrow', text: 'Helferanmeldung'}),
            element('h2', {text: block.eventTitle || 'Veranstaltung'}),
            element('p', {text: 'Schön, dass du uns unterstützen möchtest. Teile uns kurz mit, wobei du helfen kannst.'}),
        ]}),
        element('fieldset', {className: 'event-help-companion', children: [
            element('div', {className: 'event-help-companion-heading', children: [
                element('legend', {className: 'event-help-companion-title', text: 'Person 1'}),
            ]}),
            element('div', {className: 'form-grid form-grid-3', children: [field('Vorname', 'firstName'), field('Nachname', 'lastName'), field('Geburtsdatum', 'birthDate', '', 'date')]}),
        ]}),
        companions,
        addCompanion,
        ...(activityEntries.length ? [element('fieldset', {className: 'event-activity-choices', children: [
            element('legend', {text: activityInputs.length ? 'Wobei möchtest du helfen?' : 'Das steht an'}),
            ...activityEntries,
        ]})] : []),
        field('Möchtest du uns noch etwas mitteilen? (optional)', 'message', '', 'textarea'),
        element('label', {className: 'check-field', children: [privacy, element('span', {text: 'Ich stimme der Verarbeitung meiner Angaben zur Organisation dieser Veranstaltung zu.'})]}),
        message,
        submitButton,
    ]});
    form.querySelector('[name="firstName"]').required = true;
    form.querySelector('[name="lastName"]').required = true;
    form.querySelector('[name="birthDate"]').required = true;
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
                birthDate: data.get('birthDate'),
                companions: companionPayload(),
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

export {openEventHelpDialog};
