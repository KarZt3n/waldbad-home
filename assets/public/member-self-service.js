// „Meine Mitgliedschaft“: eigenständige öffentliche Ansicht außerhalb des CMS-Seitenbaums. Ohne
// `?token=` das Anfrageformular (`renderMemberAccessRequestForm`), mit Token die Passwortabfrage
// (`renderMemberSelfServicePasswordGate`) vor den nur lesbaren Mitgliedsdaten
// (`renderMemberSelfServiceData`).

import {
    app, element, field, FAMILY_ROLE_LABELS, formatDateDE, formatEuro, formMessage, MEMBER_FUNCTION_LABELS,
    PAYMENT_INTERVAL_LABELS, PAYMENT_METHOD_LABELS, request, SALUTATION_LABELS, toast,
} from '../core.js';
import {buildMemberAccessNav, buildSiteFooter, buildSiteHeader, MEMBER_ACCESS_SLUG} from './site-chrome.js';

const memberDataRow = (label, value) => element('div', {className: 'member-data-row', children: [
    element('span', {className: 'member-data-label', text: label}),
    element('span', {className: 'member-data-value', text: value === null || value === undefined || value === '' ? '–' : value}),
]});
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

export {renderMemberSelfServicePage};
