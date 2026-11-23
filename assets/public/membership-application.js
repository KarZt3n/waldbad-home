// Mitgliedsantrag als Seitenerweiterung („Mitgliedsantrag“). Eigenes Modul, das `content-rendering.js`
// erst lädt, wenn eine Seite den Block enthält (siehe `lazyExtension`).

import {confirmAction, element, field, formMessage, request, SALUTATION_LABELS, selectField, toast} from '../core.js';

// Die Texte des Formulars pflegen Redakteure unter „Seiten“ → „Erweiterungen“ (siehe `PageExtension`
// im Backend); `consentVersion` geht mit dem Antrag zurück, damit nachweisbar bleibt, welchem Wortlaut
// der Bestätigungen zugestimmt wurde.
const renderMembershipApplicationForm = (preview = false) => {
    const container = element('section', {className: 'membership-extension', attributes: {'aria-busy': 'true'}, children: [
        element('p', {className: 'empty-copy', text: 'Der Mitgliedsantrag wird geladen …'}),
    ]});
    request('/api/public/v1/page-extensions/membership_application/texts')
        .then(({texts, consentVersion}) => container.replaceChildren(buildMembershipApplicationForm(texts, consentVersion, preview)))
        .catch((error) => container.replaceChildren(element('p', {className: 'failure-message', text: `Der Mitgliedsantrag konnte nicht geladen werden: ${error.message}`})))
        .finally(() => container.removeAttribute('aria-busy'));

    return container;
};
const buildMembershipApplicationForm = (texts, consentVersion, preview) => {
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
        if (!isFirstPerson) {
            emailField.append(element('small', {className: 'field-hint membership-text', text: texts.additionalPersonEmailHint}));
        }
        const streetField = applicantField('Straße', 'street');
        const houseNumberField = applicantField('Hausnummer', 'houseNumber');
        const postalCodeField = applicantField('Postleitzahl', 'postalCode');
        const cityField = applicantField('Wohnort', 'city');
        // Für jede weitere Person wird die Anschrift als Vorschlag von Person 1 übernommen — bei
        // einer Familie im selben Haushalt muss so nicht jede Person dieselben Angaben erneut
        // eintippen. Bleibt änderbar, falls jemand eine eigene Adresse hat. Die E-Mail-Adresse wird
        // bewusst nicht übernommen (siehe Hinweistext oben): sie ist je Person optional und eigen.
        if (!isFirstPerson) {
            const firstPerson = applicants.children[0];
            const copyFromFirstPerson = (field, key) => {
                const firstInput = firstPerson?.querySelector(`[data-applicant-field="${key}"]`);
                const value = firstInput?.value.trim();
                if (value) field.querySelector('input').value = value;
            };
            copyFromFirstPerson(streetField, 'street');
            copyFromFirstPerson(houseNumberField, 'houseNumber');
            copyFromFirstPerson(postalCodeField, 'postalCode');
            copyFromFirstPerson(cityField, 'city');
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
                streetField,
                houseNumberField,
                postalCodeField,
                cityField,
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
        return element('label', {className: 'check-field', children: [input, element('span', {className: 'membership-text', text})]});
    };
    const form = element('form', {className: 'public-form membership-form', children: [
        element('header', {className: 'membership-intro', children: [
            element('p', {className: 'eyebrow', text: texts.eyebrow}),
            element('h2', {text: texts.title}),
            element('p', {className: 'membership-text', text: texts.intro}),
        ]}),
        applicants,
        addPerson,
        element('section', {className: 'membership-section', children: [
            element('h3', {text: texts.sepaTitle}),
            element('div', {className: 'form-grid', children: [
                field('Kontoinhaber', 'accountHolder'),
                field('IBAN', 'iban'),
            ]}),
        ]}),
        element('section', {className: 'membership-section membership-consents', children: [
            element('h3', {text: texts.consentsTitle}),
            consent('termsAccepted', texts.consentTerms),
            consent('privacyAccepted', texts.consentPrivacy),
            consent('sepaAccepted', texts.consentSepa),
            consent('emailConsent', texts.consentEmail, false),
            field('Name der unterzeichnenden Person', 'signerName'),
            element('small', {className: 'membership-text', text: texts.signerHint}),
        ]}),
        message,
        element('button', {className: 'button membership-submit', text: preview ? 'In der Vorschau nicht absendbar' : texts.submitLabel, attributes: {type: 'submit', ...(preview ? {disabled: 'disabled'} : {})}}),
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
                bankName: null,
                signerName: data.get('signerName'),
                termsAccepted: data.get('termsAccepted') === 'on',
                privacyAccepted: data.get('privacyAccepted') === 'on',
                sepaAccepted: data.get('sepaAccepted') === 'on',
                emailConsent: data.get('emailConsent') === 'on',
                declarationVersion: consentVersion,
            })});
            const success = element('section', {className: 'membership-success', attributes: {role: 'status'}, children: [
                element('p', {className: 'eyebrow', text: 'Antrag eingegangen'}),
                element('h2', {text: texts.successTitle}),
                element('p', {className: 'membership-text', text: texts.successMessage}),
                element('small', {text: `Vorgangsnummer: ${response.id}`}),
            ]});
            form.replaceWith(success);
            toast(texts.successMessage);
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
            submit.disabled = false;
        }
    });

    return form;
};

export {renderMembershipApplicationForm};
