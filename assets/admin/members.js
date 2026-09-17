// Modul „Mitgliederverwaltung“: Dashboard, Mitgliederliste/-stammdaten, Beitragssätze,
// Mitgliedsanträge und Mitgliedernachrichten als Unter-Reiter unter einer gemeinsamen Route
// (`/admin/mitglieder/...`).

import {
    CONTRIBUTION_CATEGORY_LABELS, confirmAction, element, FAMILY_ROLE_LABELS, field, fieldRow,
    formatDateDE, formatEuro, formMessage, MEMBER_FUNCTION_LABELS, PAYER_TYPE_LABELS,
    PAYMENT_DAY_LABELS, PAYMENT_INTERVAL_LABELS, PAYMENT_METHOD_LABELS, PERSON_GROUP_LABELS,
    radioGroup, request, SALUTATION_LABELS, selectField, toast,
} from '../core.js';
import {actionButton, actionMenu, emptyState, searchField, sectionHeading} from './ui.js';
import {canEditModule, hasModule} from './session.js';
import {ensurePinUnlocked, promptForPin} from './pin.js';
import {adminPath, setAdminPath, workspace} from './shell.js';

/**
 * Löscht ein Mitglied und berücksichtigt dabei einen möglichen PIN-Schutz für „Mitglied löschen“
 * (`members.delete`) — anders als bei `ensurePinUnlocked` bewusst ohne vorherigen, gesonderten
 * Prüf-Aufruf und ohne sitzungsweites Merken: Der erste Versuch läuft ohne PIN; ist die Aktion
 * gerade geschützt, meldet der Server das über `error.code === 'pinrequiredexception'` und genau
 * dann wird der PIN abgefragt — jedes Mal neu, da es sich um eine einzelne, endgültige Aktion
 * handelt. Liefert `true` bei Erfolg (direkt oder nach PIN-Eingabe), `false` bei „Abbrechen“.
 */
const deleteMemberWithOptionalPin = async (id) => {
    try {
        await request(`/api/admin/v1/members/${id}`, {method: 'DELETE'});

        return true;
    } catch (error) {
        if (error.code !== 'pinrequiredexception') throw error;
    }

    return promptForPin(
        'Mitglied löschen',
        'Für diese Funktion ist zusätzlich ein PIN erforderlich.',
        (pin) => request(`/api/admin/v1/members/${id}`, {method: 'DELETE', body: JSON.stringify({pin})}),
    );
};
const showMemberMessages = async () => {
    const data = await request('/api/admin/v1/member-messages');
    workspace.replaceChildren(sectionHeading('Mitgliedernachrichten', 'Über „Meine Mitgliedschaft" gesendete Nachrichten bearbeiten und abschließen'), element('div', {
        className: 'card-list', children: data.items.length ? data.items.map((item) => memberMessageCard(item, showMemberMessages)) : [emptyState('Keine Nachrichten vorhanden.')],
    }));
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
            tile('Offene Mitgliedsanträge', stats.pendingApplications),
            tile('Austritte zum Jahresende', stats.leavingAtYearEnd),
            tile('Austritte aus Vorjahr', stats.leftLastYearEnd),
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

// Für die Zahler-Suche im Dialog wird die vollständige, ungefilterte Mitgliederliste gebraucht
// (unabhängig von einer evtl. aktiven Suche in der Tabelle) — ein Zahler kann für jedes
// Mitglied bezahlen, nicht nur für den eigenen Haushalt. `payerCandidatesCache` hält diese Liste
// (als Promise, damit parallele Öffnungen sich nicht gegenseitig doppelt laden), damit das
// Öffnen eines Datensatzes selbst keinen eigenen "members"-Request mehr braucht: `showMembers`
// befüllt den Cache bereits direkt aus ihrer eigenen (bei leerer Suche ohnehin ungefilterten)
// Listenantwort mit. Erst wenn der Cache noch nie befüllt wurde (z. B. Dialog-Öffnen, bevor der
// Tab „Mitglieder" je geladen wurde), lädt `loadPayerCandidates()` hier selbst nach.
let payerCandidatesCache = null;
const invalidatePayerCandidatesCache = () => { payerCandidatesCache = null; };
const loadPayerCandidates = () => {
    // Bei einem fehlgeschlagenen Request bleibt nichts Kaputtes im Cache stehen — sonst würde
    // ein einmaliger Netzwerkfehler jeden weiteren Dialog-Aufruf bis zum nächsten erfolgreichen
    // Laden der Liste ebenfalls scheitern lassen.
    payerCandidatesCache ??= request('/api/admin/v1/members')
        .then((data) => data.items)
        .catch((error) => { invalidatePayerCandidatesCache(); throw error; });

    return payerCandidatesCache;
};

const openMemberDialog = async (member, onSaved) => {
    // Die Haushaltsdaten sind serverseitig bereits auf die Hauptnummer dieses einen Mitglieds
    // beschränkt (`GetMemberHouseholdQuery`), laden also ohnehin nur die eigene Familie.
    let [household, payerCandidates] = await Promise.all([
        member ? request(`/api/admin/v1/members/${member.id}/household`) : Promise.resolve(null),
        loadPayerCandidates(),
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

    // Vorstandsmitglieder sind laut Satzung beitragsfrei: Beim Umstellen der Funktion auf
    // „Vorstand“ wird „Beitragspflichtig“ direkt im Formular deaktiviert, beim Umstellen weg
    // von „Vorstand“ wieder aktiviert — maßgeblich (auch ohne JavaScript bzw. bei einem
    // direkten API-Aufruf) ist aber der serverseitige Automatismus in `UpdateMemberUseCase`,
    // der beim Speichern denselben Wechsel erkennt, den Haken entsprechend setzt und danach den
    // Beitrag für das Mitglied und seinen ganzen Haushalt automatisch neu berechnet.
    const memberFunctionSelect = memberFunction.querySelector('select');
    memberFunctionSelect.addEventListener('change', () => {
        contributionLiable.checked = memberFunctionSelect.value !== 'board';
        applyContributionLiableState();
    });

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
                if (!(await deleteMemberWithOptionalPin(member.id))) {
                    deleteButton.disabled = false;
                    return;
                }
                toast('Das Mitglied wurde gelöscht.');
                dialog.close();
                await onSaved();
            } catch (error) {
                toast(error.message, 'error');
                deleteButton.disabled = false;
            }
        });
    }

    // In eine eigene Funktion ausgelagert, damit „Beitrag neu berechnen“ nur diesen Ausschnitt
    // austauschen kann, statt den ganzen Dialog zu schließen (siehe Klick-Handler unten).
    const contributionInfoContent = (currentMember) => {
        const exemptionReason = contributionExemptionReason(currentMember);

        return [
            element('p', {children: [
                element('strong', {text: exemptionReason
                    ?? (currentMember.contributionCategory ? CONTRIBUTION_CATEGORY_LABELS[currentMember.contributionCategory] || currentMember.contributionCategory : 'Noch nicht berechnet')}),
                element('span', {text: ` · ${formatEuro(exemptionReason ? 0 : currentMember.contributionAmountCents)} pro Jahr`}),
            ]}),
            ...(!exemptionReason && currentMember.workAssignmentSurchargeCents != null ? [element('p', {children: [
                element('strong', {text: 'Arbeitseinsatz-Zuschlag'}),
                element('span', {text: ` · zzgl. ${formatEuro(currentMember.workAssignmentSurchargeCents)} pro Jahr (bei Ableistung der Gemeinschaftsstunden erstattungsfähig)`}),
            ]})] : []),
            element('p', {className: 'field-hint', text: 'Erhaltene Beitragssätze (einmalige Gebühren):'}),
            element('ul', {className: 'member-remarks', children: currentMember.oneTimeCharges.length
                ? currentMember.oneTimeCharges.map((charge) => element('li', {children: [
                    element('strong', {text: charge.label}),
                    element('span', {text: ` · ${formatEuro(charge.amountCents)} · ${new Date(charge.chargedAt).toLocaleDateString('de-DE')}`}),
                ]}))
                : [element('li', {className: 'empty-copy', text: 'Keine einmaligen Gebühren berechnet.'})]}),
        ];
    };
    const contributionInfo = member ? element('div', {children: contributionInfoContent(member)}) : null;
    const contributionSection = member ? element('fieldset', {children: [
        element('legend', {text: 'Beitrag'}),
        contributionInfo,
    ]}) : null;
    const recalculate = member ? element('button', {className: 'secondary-button', text: 'Beitrag neu berechnen', attributes: {type: 'button'}}) : null;
    if (recalculate) {
        recalculate.addEventListener('click', async () => {
            // Erst die aktuell im Formular eingetragenen Werte speichern (z. B. eine gerade erst
            // umgestellte Funktion), sonst würde die Berechnung mit dem zuletzt gespeicherten
            // Stand aus der Datenbank rechnen statt mit den noch ungespeicherten Änderungen.
            // `reportValidity()` entspricht derselben Pflichtfeld-Prüfung wie beim „Speichern“.
            if (!form.reportValidity()) return;
            recalculate.disabled = true;
            try {
                member = await persistMember();
                member = await request(`/api/admin/v1/members/${member.id}/recalculate-contribution`, {method: 'POST'});
                // Bewusst kein dialog.close(): nur die Beitragsdaten des gerade geöffneten
                // Mitglieds werden hier neu geladen und ausgetauscht — der Dialog bleibt offen.
                household = await request(`/api/admin/v1/members/${member.id}/household`);
                contributionInfo.replaceChildren(...contributionInfoContent(member));
                if (payerBody) payerBody.replaceChildren(...payerContent(household, member));
                // Speichern bzw. die Neuberechnung können „Beitragspflichtig“ serverseitig
                // verändert haben (z. B. wenn jetzt ein Vorstandsmitglied im Haushalt ist) —
                // Haken und davon abhängige gesperrte Felder im offenen Formular nachziehen.
                contributionLiable.checked = member.contributionLiable !== false;
                applyContributionLiableState();
                toast('Die Änderungen wurden gespeichert und der Beitrag für die ganze Familie neu berechnet.');
                await onSaved();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
            } finally {
                recalculate.disabled = false;
            }
        });
        contributionSection.append(
            recalculate,
            element('small', {text: 'Speichert zuerst alle Änderungen auf diesem Formular und berechnet danach den Beitrag für die gesamte Familie (alle Mitglieder mit derselben Hauptnummer) neu.'}),
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

    // Ebenfalls ausgelagert, damit „Beitrag neu berechnen“ diesen Ausschnitt austauschen kann,
    // ohne den Dialog zu schließen (siehe Klick-Handler oben bei `recalculate`).
    const payerContent = (currentHousehold, currentMember) => [
        element('p', {className: 'field-hint', text: currentHousehold.payer.id === currentMember.id
            ? 'Dieses Mitglied zahlt selbst, zusammen für:'
            : `Der Beitrag wird gezahlt von ${currentHousehold.payer.firstName} ${currentHousehold.payer.lastName} (${currentHousehold.payer.memberNumber}), zusammen für:`}),
        element('ul', {className: 'member-payer-list', children: currentHousehold.payerEntries.map((entry) => payerListItem(entry, currentMember.id, openOther))}),
        element('div', {className: 'member-payer-total', children: [
            element('span', {text: 'Gesamtbeitrag pro Jahr'}),
            // Aus denselben Positionen wie die Liste darüber berechnet (statt aus dem vom
            // Server gelieferten Rohwert), damit ein gerade erst gesetztes „Beitragspflichtig“-
            // Häkchen die Summe sofort auf 0 € zieht, statt erst nach „Beitrag neu berechnen“.
            element('span', {text: formatEuro(currentHousehold.payerEntries.reduce(
                (sum, entry) => sum + contributionPositions(entry).reduce((entrySum, [, cents]) => entrySum + (cents || 0), 0),
                0,
            ))}),
        ]}),
    ];
    const payerBody = member ? element('div', {children: payerContent(household, member)}) : null;
    const payerSection = member ? element('fieldset', {children: [
        element('legend', {text: 'Gesamtberechnung für den Zahler'}),
        payerBody,
    ]}) : null;

    // Ausgelagert, damit der Klick auf „E-Mail-Einwilligung senden“ nur diesen Status-Text
    // austauschen kann, ohne den Dialog zu schließen (siehe `recalculate` oben für dasselbe Muster).
    const emailConsentStatusContent = (currentMember) => `E-Mail-Einwilligung erteilt: ${currentMember.emailConsent ? 'Ja' : 'Nein'}`;
    const emailConsentStatus = member ? element('p', {className: 'field-hint', text: emailConsentStatusContent(member)}) : null;
    const sendEmailConsentRequest = member ? element('button', {className: 'secondary-button', text: 'E-Mail-Einwilligung senden', attributes: {type: 'button'}}) : null;
    const sendEmailConsentRequestHint = member ? element('small', {text: 'Verschickt einen Bestätigungslink an die hinterlegte E-Mail-Adresse (Doppel-Opt-in) — die Einwilligung gilt erst als erteilt, sobald das Mitglied den Link anklickt.'}) : null;
    if (sendEmailConsentRequestHint) sendEmailConsentRequestHint.hidden = member.emailConsent;
    if (sendEmailConsentRequest) {
        sendEmailConsentRequest.hidden = member.emailConsent;
        sendEmailConsentRequest.disabled = !member.email;
        sendEmailConsentRequest.addEventListener('click', async () => {
            sendEmailConsentRequest.disabled = true;
            try {
                member = await request(`/api/admin/v1/members/${member.id}/email-consent-requests`, {method: 'POST'});
                emailConsentStatus.textContent = emailConsentStatusContent(member);
                toast('Der Bestätigungslink wurde per E-Mail verschickt.');
            } catch (error) {
                toast(error.message, 'error');
            } finally {
                sendEmailConsentRequest.disabled = false;
            }
        });
    }

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
            element('fieldset', {children: [
                element('legend', {text: 'Kontakt'}),
                email, phone,
                ...(emailConsentStatus ? [
                    emailConsentStatus,
                    sendEmailConsentRequest,
                    sendEmailConsentRequestHint,
                ] : []),
            ]}),
        ]],
        ['verein', 'Vereinsdaten', [
            element('fieldset', {children: [
                element('legend', {text: 'Vereinsdaten'}),
                memberFunction,
                fieldRow([joinedAt, leftAt]),
            ]}),
        ]],
        ['beitrag', 'Beitragsdaten', [
            element('fieldset', {children: [
                element('legend', {text: 'Beitragspflicht'}),
                element('label', {className: 'check-field', children: [contributionLiable, element('span', {text: 'Beitragspflichtig'})]}),
                element('small', {text: 'Vorstandsmitglieder sind laut Satzung beitragsfrei — und mit ihnen ihre ganze Familie: Sobald „Beitrag neu berechnen“ läuft (hier direkt oder über „Beiträge für alle Mitglieder neu berechnen“), wird dieser Haken bei allen Mitgliedern der Familie automatisch entfernt, sobald mindestens eines von ihnen Vorstand ist. Beim Umstellen der Funktion auf/von „Vorstand“ (Reiter „Vereinsdaten“) passiert dasselbe zusätzlich direkt beim Speichern. Ist der Haken entfernt, werden Konto-, Bank- und Zahlungsdaten gesperrt und der Beitrag mit 0 € und dem Hinweis „Vorstand“ geführt.'}),
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
    // In eine eigene Funktion ausgelagert, damit „Beitrag neu berechnen“ (siehe oben bei
    // `recalculate`) dieselben, gerade im Formular eingetragenen Werte speichern kann, statt
    // die zuletzt gespeicherten Daten aus der Datenbank neu zu berechnen — sonst müsste man vor
    // „Beitrag neu berechnen“ immer erst manuell „Änderungen speichern“ klicken, z. B. nach
    // einer Umstellung der Funktion auf „Vorstand“.
    const buildPayload = () => ({
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
    });
    const persistMember = () => member
        ? request(`/api/admin/v1/members/${member.id}`, {
            method: 'PUT',
            body: JSON.stringify({...buildPayload(), memberNumber: member.memberNumber, version: member.version}),
        })
        : request('/api/admin/v1/members', {method: 'POST', body: JSON.stringify(buildPayload())});
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        try {
            const wasNew = !member;
            member = await persistMember();
            toast(wasNew ? 'Das Mitglied wurde angelegt.' : 'Das Mitglied wurde gespeichert.');
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

// Zeigt, welche Mitglieder eine Sammel-Neuberechnung übersprungen hat und warum — damit z. B.
// eine fehlende/ungültige IBAN nicht als anonyme Fehlermeldung ohne erkennbaren Bezug zu einem
// Mitglied endet (`RecalculateAllMemberContributionsUseCase` liefert dafür je übersprungenem
// Datensatz die Mitgliedsnummer mit).
const openRecalculationErrorsDialog = (errors) => {
    const dialog = element('dialog', {className: 'activity-dialog'});
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
    const closeButton = element('button', {className: 'secondary-button', text: 'Schließen', attributes: {type: 'button'}});
    const content = element('div', {className: 'activity-dialog-content', children: [
        element('header', {children: [
            element('div', {children: [
                element('p', {className: 'eyebrow', text: 'Mitglieder'}),
                element('h2', {text: 'Nicht neu berechnete Mitglieder'}),
            ]}),
        ]}),
        element('p', {className: 'field-hint', text: `${errors.length} Mitglied(er) konnten nicht neu berechnet werden und blieben unverändert:`}),
        element('ul', {className: 'member-remarks', children: errors.map((error) => element('li', {children: [
            element('strong', {text: error.memberNumber}),
            element('p', {text: error.message}),
        ]}))}),
        element('div', {className: 'confirm-dialog-actions', children: [closeButton]}),
    ]});
    closeButton.addEventListener('click', () => dialog.close());
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(close, content);
    document.body.append(dialog);
    dialog.showModal();
};

let memberSearchTerm = '';
let memberSortField = 'primaryMemberNumber';
let memberSortDirection = 'asc';
let memberStatusFilter = '';

// Laut Beitrags- und Kassenordnung ist eine Kündigung nur fristgemäß zum Jahresende möglich —
// ein gesetztes Austrittsdatum liegt praktisch immer auf den 31.12. des jeweiligen Jahres (siehe
// auch `GetMembershipDashboardQuery::$leavingAtYearEnd`/`$leftLastYearEnd`, dieselbe Definition).
const isLeavingAtYearEnd = (leftAt) => !!leftAt && leftAt === `${new Date().getFullYear()}-12-31`;
const isLeftLastYearEnd = (leftAt) => !!leftAt && leftAt === `${new Date().getFullYear() - 1}-12-31`;
// Läd bewusst nicht (mehr) blockierend: der reale Mitgliederbestand (aktuell ~1000
// Datensätze, Sage-GS-Import) lässt die Abfrage spürbar dauern. Kopf/Suche/Filter werden
// daher sofort gerendert, die Tabelle zeigt währenddessen einen Ladehinweis und wird ersetzt,
// sobald die Antwort da ist. `membersLoadToken` verwirft veraltete Antworten, falls Suche,
// Filter oder Tab in der Zwischenzeit erneut wechseln.
let membersLoadToken = 0;
const showMembers = () => {
    const heading = sectionHeading('Mitglieder', 'Stammdaten aller Vereinsmitglieder verwalten');
    const actions = [];
    if (canEditModule('members')) {
        const create = element('button', {className: 'button', text: '＋ Neues Mitglied', attributes: {type: 'button'}});
        create.addEventListener('click', () => openMemberDialog(null, showMembershipManagement));
        actions.push(create);
    }
    if (canEditModule('members')) {
        // Bewusst kein generischer `actionButton`: der Antwortkörper enthält je übersprungenem
        // Mitglied dessen Mitgliedsnummer und den Grund (`errors`) — die müssen sichtbar
        // gemacht werden, statt wie bei `actionButton` ungelesen zu verfallen.
        const recalculateAll = element('button', {className: 'secondary-button', text: 'Beiträge für alle Mitglieder neu berechnen', attributes: {type: 'button'}});
        recalculateAll.addEventListener('click', async () => {
            const confirmed = await confirmAction(
                'Beiträge neu berechnen',
                'Der Beitrag wird für alle Mitglieder anhand der aktuellen Beitragssätze und Altersspannen neu berechnet. Das kann nicht rückgängig gemacht werden.',
                'Neu berechnen',
            );
            if (!confirmed) return;
            recalculateAll.disabled = true;
            try {
                const result = await request('/api/admin/v1/members/recalculate-contributions', {method: 'POST'});
                if (result.errors.length) {
                    toast(`${result.updated} Mitglied(er) aktualisiert, ${result.errors.length} übersprungen — siehe Liste.`, 'info');
                    openRecalculationErrorsDialog(result.errors);
                } else {
                    toast(`Die Beiträge wurden für alle ${result.updated} Mitglieder neu berechnet.`);
                }
                await showMembershipManagement();
            } catch (error) {
                toast(error.message, 'error');
            } finally {
                recalculateAll.disabled = false;
            }
        });
        actions.push(recalculateAll);
    }
    if (canEditModule('members')) {
        actions.push(actionMenu('Datenbank', [
            {label: 'Importieren …', run: () => openMemberImportDialog(showMembershipManagement)},
            {label: 'Exportieren …', run: () => openMemberExportDialog()},
        ]));
    }


    const search = searchField('Volltextsuche: Nummer, Name, Straße, PLZ, Ort, E-Mail, Telefon …', memberSearchTerm, async (value) => {
        memberSearchTerm = value;
        await showMembershipManagement();
    });

    const statusFilter = element('select', {attributes: {'aria-label': 'Nach Status filtern'}, children: [
        element('option', {text: 'Alle', attributes: {value: ''}}),
        element('option', {text: 'Aktiv', attributes: {value: 'active'}}),
        element('option', {text: 'Inaktiv', attributes: {value: 'inactive'}}),
        element('option', {text: 'Austritte zum Jahresende', attributes: {value: 'leaving_year_end'}}),
        element('option', {text: 'Austritte aus Vorjahr', attributes: {value: 'left_last_year_end'}}),
    ]});
    statusFilter.value = memberStatusFilter;
    statusFilter.addEventListener('change', async () => {
        memberStatusFilter = statusFilter.value;
        await showMembershipManagement();
    });

    const countLabel = element('span', {text: 'Mitglieder werden geladen …'});
    // `table-scroll-area` (nicht nur `.data-table` selbst) braucht die Overflow-Grenze: die
    // Tabelle steckt in einem Grid mit `1fr`-Spalte (`.admin-layout`), und ohne einen
    // Nachfahren mit `overflow-x`/`max-width` zwischen ihr und dem Grid-Item `.admin-workspace`
    // sprengt ihre (durch `white-space: nowrap` unzerteilbare) Breite die Spurbreite und
    // drückt die Sidebar-Spalte schmaler, statt selbst zu scrollen.
    const tableArea = element('div', {className: 'table-scroll-area', children: [emptyState('Mitglieder werden geladen …')]});

    workspace.replaceChildren(
        element('div', {className: 'management-header', children: [heading, element('div', {className: 'management-actions', children: actions})]}),
        element('div', {className: 'management-toolbar', children: [search, statusFilter, countLabel]}),
        tableArea,
    );

    const loadToken = ++membersLoadToken;
    (async () => {
        let data;
        try {
            const query = memberSearchTerm ? `?search=${encodeURIComponent(memberSearchTerm)}` : '';
            data = await request('/api/admin/v1/members' + query);
        } catch (error) {
            if (loadToken !== membersLoadToken) return;
            countLabel.textContent = '';
            tableArea.replaceChildren(emptyState('Die Mitgliederliste konnte nicht geladen werden.'));
            toast(error.message, 'error');
            return;
        }
        if (loadToken !== membersLoadToken) return;

        // Diese Liste ist — sofern gerade keine Suche aktiv ist — bereits die vollständige,
        // ungefilterte Mitgliederliste: dieselben Daten, die `loadPayerCandidates()` sonst per
        // eigenem Request nachladen würde. Hier direkt als Cache übernehmen, damit das Öffnen
        // eines Datensatzes (`openMemberDialog`) nicht zusätzlich "members" anfragt, sondern nur
        // noch den Haushalt lädt. Bei aktiver Suche bleibt ein zuvor gefüllter Cache unangetastet
        // (kein Überschreiben mit der gefilterten Teilmenge) und wird beim nächsten ungefilterten
        // Laden wieder aufgefrischt.
        if (!memberSearchTerm) payerCandidatesCache = Promise.resolve(data.items);

        const filteredItems = data.items.filter((memberItem) => {
            if (memberStatusFilter === 'active') return memberItem.active;
            if (memberStatusFilter === 'inactive') return !memberItem.active;
            if (memberStatusFilter === 'leaving_year_end') return isLeavingAtYearEnd(memberItem.leftAt);
            if (memberStatusFilter === 'left_last_year_end') return isLeftLastYearEnd(memberItem.leftAt);

            return true;
        });

        const columns = [
            {key: 'memberNumber', label: 'Mitgl.-Nr.'},
            {key: 'primaryMemberNumber', label: 'Hauptnr.'},
            {key: 'lastName', label: 'Name'},
            {key: 'firstName', label: 'Vorname'},
            {key: 'birthDate', label: 'Geburtsdatum'},
            {key: 'street', label: 'Straße'},
            {key: 'city', label: 'Ort'},
        ];
        const compareMemberValues = (left, right) => {
            if (left == null && right == null) return 0;
            if (left == null) return -1;
            if (right == null) return 1;

            return String(left).localeCompare(String(right), 'de', {numeric: true, sensitivity: 'base'});
        };

        const buildTable = () => {
            const sortedItems = [...filteredItems].sort((left, right) => {
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
                        memberItem.lastName,
                        memberItem.firstName,
                        new Date(`${memberItem.birthDate}T00:00:00`).toLocaleDateString('de-DE'),
                        memberItem.street,
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

        countLabel.textContent = memberStatusFilter
            ? `${filteredItems.length} von ${data.total} Mitglieder`
            : `${data.total} Mitglieder`;
        tableArea.replaceChildren(filteredItems.length
            ? table
            : emptyState(data.items.length ? 'Keine Mitglieder für diesen Filter.' : 'Noch keine Mitglieder angelegt.'));
    })();
};

const openContributionRateDialog = (rate, existingRates, onSaved, settings) => {
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

    const pending = rate?.pending || null;
    const validFrom = field('Gültig ab', `rate-valid-from-${suffix}`, settings.validFrom || '', 'date');
    validFrom.append(element('small', {className: 'field-hint', text: 'Ein zukünftiges Datum speichert alle Änderungen als Pending. Heute oder früher übernimmt sie sofort.'}));
    if (pending) {
        const plannedFields = [
            [label, 'label', pending.label],
            [amount, 'amountCents', formatEuro(pending.amountCents)],
            [period, 'period', PAYMENT_INTERVAL_LABELS[pending.period]],
            [personGroup, 'personGroup', PERSON_GROUP_LABELS[pending.personGroup] || 'Keiner (gilt für alle)'],
            [minAge, 'minAge', pending.minAge ?? 'Keine Untergrenze'],
            [maxAge, 'maxAge', pending.maxAge ?? 'Keine Obergrenze'],
            [validFrom, 'validFrom', formatDateDE(pending.validFrom)],
        ];
        plannedFields.forEach(([control, key, value]) => {
            if (key !== 'validFrom' && pending[key] === rate[key]) return;
            const hint = element('small', {className: 'field-hint', text: `wird geändert zu: ${value} ab ${formatDateDE(pending.validFrom)}`});
            hint.id = `${control.querySelector('input, select').id}-pending`;
            control.querySelector('input, select').setAttribute('aria-describedby', hint.id);
            control.append(hint);
        });
    }
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
        validFrom,
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
        const effectiveDate = validFrom.querySelector('input').value;
        const unchanged = rate && ['label', 'amountCents', 'period', 'personGroup', 'minAge', 'maxAge'].every((key) => payload[key] === rate[key]);
        if (rate && effectiveDate && !(unchanged && effectiveDate === (settings.validFrom || ''))) {
            payload.validFrom = effectiveDate;
        } else if (pending) {
            payload.pending = pending;
        }
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

/**
 * Das gemeinsame „gültig ab" für alle Beitragssätze (siehe `ContributionRateSettings`) — anders
 * als eine geplante Betragsänderung je Beitragssatz gilt dies für alle gemeinsam. Rückt
 * außerdem automatisch vor, sobald eine geplante Änderung greift (dann nur lesend sichtbar,
 * hier aber weiterhin von Hand überschreibbar).
 */
const renderContributionRateSettingsCard = (settings, onSaved) => {
    const message = formMessage();
    const validFrom = field('Gültig ab (für alle Beitragssätze)', 'contribution-rate-settings-valid-from', settings.validFrom || '', 'date');
    const form = element('form', {className: 'compact-form contribution-rate-settings-card', children: [
        element('h3', {text: 'Beitragsordnung'}),
        element('p', {className: 'field-hint', text: 'Gilt für alle Beitragssätze gemeinsam — wird in „Meine Mitgliedschaft" bei der Gesamtberechnung angezeigt. Rückt automatisch vor, sobald eine geplante Betragsänderung (siehe einzelne Beitragssätze unten) greift.'}),
        validFrom,
        message,
        element('button', {className: 'button', text: 'Speichern', attributes: {type: 'submit'}}),
    ]});
    if (!canEditModule('contribution_rates')) {
        validFrom.querySelector('input').disabled = true;
        form.querySelector('button').hidden = true;
    }
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            await request('/api/admin/v1/contribution-rate-settings', {
                method: 'PUT',
                body: JSON.stringify({validFrom: validFrom.querySelector('input').value || null}),
            });
            toast('Gespeichert.');
            await onSaved();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    return form;
};

const showContributionRates = async () => {
    const data = await request('/api/admin/v1/contribution-rates');
    const settings = await request('/api/admin/v1/contribution-rate-settings');
    const heading = sectionHeading('Beitragssätze', 'Bezeichnung, Betrag, Zeitraum und Altersspanne je Beitragssatz pflegen');
    if (canEditModule('contribution_rates')) {
        const create = element('button', {className: 'button', text: '＋ Neuer Beitragssatz', attributes: {type: 'button'}});
        create.addEventListener('click', () => openContributionRateDialog(null, data.items, showMembershipManagement, settings));
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
                        rate.pending ? `Änderung ab ${formatDateDE(rate.pending.validFrom)}` : null,
                    ].filter(Boolean).join(' · ')}),
                ]}),
                element('span', {className: 'status-badge', text: `${formatEuro(rate.amountCents)} · ${PAYMENT_INTERVAL_LABELS[rate.period] || rate.period}`}),
                ...(rate.status === 'pending' ? [element('span', {className: 'status-badge status-pending', text: 'Pending'})] : []),
                ...(isOnce ? [] : [element('span', {className: 'status-badge', text: `${formatEuro(rate.annualAmountCents)} / Jahr`})]),
                ...(canEditModule('contribution_rates') ? [element('span', {className: 'activity-list-edit', text: 'Bearbeiten ›'})] : []),
            ],
        });
        if (canEditModule('contribution_rates')) row.addEventListener('click', () => openContributionRateDialog(rate, data.items, showMembershipManagement, settings));

        return row;
    });
    workspace.replaceChildren(
        heading,
        element('div', {className: 'activity-list', children: rows.length ? rows : [emptyState('Keine Beitragssätze vorhanden.')]}),
        renderContributionRateSettingsCard(settings, showContributionRates),
    );
};

const memberMessageCard = (item, refresh) => element('article', {className: 'management-card', children: [
    element('header', {children: [
        element('strong', {text: `${item.memberName} (${item.memberNumber})`}),
        element('small', {text: item.status + ' · ' + new Date(item.submittedAt).toLocaleString('de-DE')}),
    ]}),
    element('p', {text: item.message}),
    ...(canEditModule('member_messages') ? [element('div', {className: 'card-actions', children: [
        actionButton('In Bearbeitung', `/api/admin/v1/member-messages/${item.id}/status/in_progress`, refresh, 'secondary-button', {success: 'Nachricht ist jetzt in Bearbeitung.'}),
        actionButton('Erledigt', `/api/admin/v1/member-messages/${item.id}/status/resolved`, refresh, 'button', {success: 'Nachricht wurde als erledigt markiert.'}),
    ]})] : []),
]});

const membershipTabsBySlug = {
    dashboard: 'dashboard',
    mitglieder: 'members',
    beitragssaetze: 'rates',
    antraege: 'applications',
    nachrichten: 'messages',
};
const membershipSlugsByTab = Object.fromEntries(Object.entries(membershipTabsBySlug).map(([slug, tab]) => [tab, slug]));
let activeMembershipTab = null;

const showMembershipManagement = async (segments = []) => {
    if (segments[0] === 'mitglieder' && membershipTabsBySlug[segments[1]]) {
        activeMembershipTab = membershipTabsBySlug[segments[1]];
    }
    const tabs = [
        ...(hasModule('members') ? [['dashboard', 'Dashboard', showMembershipDashboard]] : []),
        ...(hasModule('members') ? [['members', 'Mitglieder', showMembers]] : []),
        ...(hasModule('contribution_rates') ? [['rates', 'Beitragssätze', showContributionRates]] : []),
        ...(hasModule('membership_applications') ? [['applications', 'Mitgliedsanträge', showMembership]] : []),
        ...(hasModule('member_messages') ? [['messages', 'Mitgliedernachrichten', showMemberMessages]] : []),
    ];
    if (!tabs.some(([key]) => key === activeMembershipTab)) activeMembershipTab = tabs[0]?.[0] || null;
    if (activeMembershipTab) {
        setAdminPath(['mitglieder', membershipSlugsByTab[activeMembershipTab]], true);
    }

    const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'Mitgliederverwaltung'}, children: tabs.map(([key, label]) => {
        const button = element('a', {
            className: `sub-tab${key === activeMembershipTab ? ' active' : ''}`,
            text: label,
            attributes: {href: adminPath('mitglieder', membershipSlugsByTab[key])},
        });
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            activeMembershipTab = key;
            setAdminPath(['mitglieder', membershipSlugsByTab[key]]);
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

export {showMembershipManagement};
