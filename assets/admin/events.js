// Modul „Veranstaltung“: Veranstaltungen, Veranstaltungshelfer (Anmeldungen, Broadcast-Mails) und
// Aktivitäten als Unter-Reiter unter einer gemeinsamen Route (`/admin/veranstaltungen/...`).

import {
    buildPageTree, confirmAction, element, field, flattenPageTree, formMessage, request, toast,
} from '../core.js';
import {actionButton, actionMenu, emptyState, searchField, sectionHeading} from './ui.js';
import {canEditModule, hasModule} from './session.js';
import {collectionItemMediaEditor, richTextEditor} from './pages.js';
import {adminPath, setAdminPath, workspace} from './shell.js';

const EVENT_SCHEDULE_KIND_LABELS = {event: 'Veranstaltung', work_assignment: 'Arbeitseinsatz'};
/**
 * Buckets items with a `YYYY-MM-DD` date (and `HH:MM` time) into „Heute“, „Kommende“,
 * „Abgeschlossen (aktuelles Jahr)“ and an archive grouped by year — the grouping used by the
 * „Veranstaltungshelfer“ and „Veranstaltungen“ admin modules.
 */
const bucketItemsByDate = (items, getDate, getTime) => {
    const now = new Date();
    const currentYear = now.getFullYear();
    const today = [currentYear, now.getMonth() + 1, now.getDate()]
        .map((part, index) => index === 0 ? String(part) : String(part).padStart(2, '0'))
        .join('-');
    const compareAscending = (first, second) => {
        const dateComparison = getDate(first).localeCompare(getDate(second));

        return dateComparison !== 0 ? dateComparison : getTime(first).localeCompare(getTime(second));
    };
    const compareDescending = (first, second) => compareAscending(second, first);
    const todayItems = items.filter((item) => getDate(item) === today).sort(compareAscending);
    const upcomingItems = items.filter((item) => getDate(item) > today).sort(compareAscending);
    const completedCurrentYearItems = items
        .filter((item) => getDate(item) < today && Number.parseInt(getDate(item).slice(0, 4), 10) === currentYear)
        .sort(compareDescending);
    const archiveByYear = items
        .filter((item) => getDate(item) < today && Number.parseInt(getDate(item).slice(0, 4), 10) < currentYear)
        .reduce((years, item) => {
            const year = Number.parseInt(getDate(item).slice(0, 4), 10);
            if (!years.has(year)) years.set(year, []);
            years.get(year).push(item);

            return years;
        }, new Map());
    archiveByYear.forEach((yearItems) => yearItems.sort(compareDescending));

    return {currentYear, todayItems, upcomingItems, completedCurrentYearItems, archiveByYear};
};
const showEventHelpers = async () => {
    const data = await request('/api/admin/v1/event-help-requests');
    // `showEventManagement()` (aufgerufen nach jeder Teilnahme-/Verknüpfungsänderung) baut die
    // gesamte Sektion neu auf — ohne das würden alle Archiv-Accordions (Jahres-Archiv, „Nicht
    // teilgenommen" je Veranstaltung) dabei wieder zuklappen. Der Zustand wird deshalb vor dem
    // Neuaufbau aus dem noch alten DOM gelesen (siehe `data-archive-key` unten) und beim Aufbau
    // der neuen `<details>`-Elemente wiederhergestellt.
    const previouslyOpenArchiveKeys = new Set(
        [...workspace.querySelectorAll('details.event-helper-archive[open]')]
            .map((details) => details.dataset.archiveKey)
            .filter(Boolean),
    );
    const saveParticipation = async (requestItem, participated, intervals = []) => {
        await request(`/api/admin/v1/event-help-requests/${requestItem.id}/participation`, {
            method: 'POST',
            body: JSON.stringify({participated, intervals}),
        });
        toast(participated ? 'Teilnahme und Helferstunden wurden gespeichert.' : 'Die Person wurde als nicht teilgenommen markiert.');
        await showEventManagement();
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
    };
    // Eigener, schlanker Such-Endpunkt statt der vollständigen Mitgliederliste (siehe
    // `AdminEventHelpRequestController::memberCandidates`) — läuft ohne Mitgliederverwaltung-Recht.
    // Der Dialog deckt zwei Fälle in einem ab: erstmalig verknüpfen bzw. eine bestehende (ggf.
    // falsche) Verknüpfung nachträglich ändern/lösen. Vor-/Nachname der Anmeldung werden dabei
    // automatisch vom ausgewählten Mitglied übernommen (korrigiert so nebenbei einen Tippfehler,
    // der das automatische Matching verhindert haben könnte) — eigene Namensfelder braucht es
    // dafür nicht.
    //
    // `items` ist eine Liste statt einer einzelnen Anmeldung, weil mehrfache Anmeldungen
    // derselben Person zur selben Veranstaltung (siehe `buildParticipantEntries`) zu einer
    // Mitgliedskarte zusammengeführt werden — Verknüpfen/Bearbeiten/Lösen wirkt dann auf alle
    // zusammengeführten Anmeldungen gleichzeitig, damit sie nicht wieder auseinanderfallen.
    const openLinkMemberDialog = (items) => {
        const primary = items[0];
        const dialog = element('dialog', {className: 'confirm-dialog'});
        const message = formMessage();
        let selectedMember = primary.memberId ? {
            id: primary.memberId, firstName: primary.memberFirstName, lastName: primary.memberLastName,
            memberNumber: primary.memberNumber,
        } : null;

        const linkStatus = element('p', {className: 'member-link-status'});
        const removeLink = element('button', {className: 'text-button danger', text: 'Verknüpfung entfernen', attributes: {type: 'button'}});
        const updateLinkStatus = () => {
            linkStatus.textContent = selectedMember
                ? `Verknüpft mit ${selectedMember.firstName} ${selectedMember.lastName} (${selectedMember.memberNumber})`
                : 'Nicht mit einem Mitglied verknüpft.';
            removeLink.hidden = !selectedMember;
        };
        removeLink.addEventListener('click', () => {
            selectedMember = null;
            updateLinkStatus();
            results.querySelectorAll('.member-link-candidate.is-selected').forEach((button) => button.classList.remove('is-selected'));
        });
        updateLinkStatus();

        const results = element('div', {className: 'member-link-results'});
        // Verhindert, dass ein Klick auf ein Suchergebnis zunächst das Suchfeld verlässt (Blur):
        // Das löst sonst — weil der Browser den bereits per Enter ausgelösten `change` nicht als
        // „committed" zählt — beim Blur ein zweites, natives `change`-Ereignis aus, das die
        // Ergebnisliste neu rendert und damit den gerade angeklickten Button unter dem Zeiger
        // entfernt, bevor dessen Klick überhaupt ankommt — der erste Klick auf ein Ergebnis blieb
        // dadurch wirkungslos, erst ein zweiter traf noch ein stabiles Element.
        results.addEventListener('mousedown', (event) => event.preventDefault());
        const renderResults = (candidates) => {
            results.replaceChildren(...(candidates.length ? candidates.map((candidate) => {
                const pick = element('button', {
                    className: `secondary-button member-link-candidate${selectedMember && selectedMember.id === candidate.id ? ' is-selected' : ''}`,
                    attributes: {type: 'button'},
                    children: [
                        element('strong', {text: `${candidate.firstName} ${candidate.lastName} (${candidate.memberNumber})`}),
                        element('small', {text: `${candidate.street} • ${new Date(`${candidate.birthDate}T00:00:00`).toLocaleDateString('de-DE')}`}),
                    ],
                });
                pick.addEventListener('click', () => {
                    selectedMember = candidate;
                    updateLinkStatus();
                    results.querySelectorAll('.member-link-candidate').forEach((button) => {
                        button.classList.toggle('is-selected', button === pick);
                    });
                });

                return pick;
            }) : [emptyState('Keine Mitglieder gefunden.')]));
        };
        const search = searchField('Name oder Mitgliedsnummer suchen …', '', async (term) => {
            if (term === '') { results.replaceChildren(); return; }
            const data = await request(`/api/admin/v1/event-help-requests/member-candidates?search=${encodeURIComponent(term)}`);
            renderResults(data.items);
        });
        // Enter im Suchfeld soll die Suche auslösen (wie ein Bestätigen/Verlassen des Felds),
        // nicht das ganze Formular abschicken — Speichern (Verknüpfen) erfolgt nur per Klick.
        const searchInput = search.querySelector('input');
        searchInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            searchInput.dispatchEvent(new Event('change'));
        });

        const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
        const save = element('button', {className: 'button button-compact', text: 'Verknüpfen', attributes: {type: 'submit'}});
        cancel.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => dialog.remove());
        const form = element('form', {className: 'confirm-dialog-content', children: [
            element('p', {className: 'eyebrow', text: 'Mitglied verknüpfen'}),
            element('h2', {text: `${primary.firstName} ${primary.lastName}`}),
            ...(items.length > 1 ? [element('p', {className: 'member-link-status', text: `${items.length} zusammengeführte Anmeldungen zu dieser Veranstaltung — die Verknüpfung gilt für alle.`})] : []),
            linkStatus,
            element('div', {className: 'member-link-status-actions', children: [removeLink]}),
            search,
            results,
            message,
            element('div', {className: 'confirm-dialog-actions', children: [cancel, save]}),
        ]});
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            save.disabled = true;
            try {
                await Promise.all(items.map((item) => request(`/api/admin/v1/event-help-requests/${item.id}/member`, {
                    method: 'POST',
                    body: JSON.stringify({
                        memberId: selectedMember ? selectedMember.id : '',
                        firstName: selectedMember ? selectedMember.firstName : item.firstName,
                        lastName: selectedMember ? selectedMember.lastName : item.lastName,
                    }),
                })));
                toast(selectedMember ? 'Die Helferanmeldung wurde mit dem Mitglied verknüpft.' : 'Die Verknüpfung wurde entfernt.');
                dialog.close();
                await showEventManagement();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
                save.disabled = false;
            }
        });
        dialog.append(form);
        document.body.append(dialog);
        dialog.showModal();
    };
    // Manuelles Hinzufügen einer Person als Helfer zu einer Veranstaltung (Button „+" neben
    // „Mail an alle Helfer") — für Fälle, in denen jemand nicht über das öffentliche Formular
    // angemeldet hat (z. B. spontan vor Ort). Nutzt denselben Mitglied-Such-Endpunkt wie
    // „Mitglied verknüpfen" (siehe `openLinkMemberDialog` oben) und `AddEventHelpRequestUseCase`.
    const openAddEventHelperDialog = (event) => {
        const dialog = element('dialog', {className: 'confirm-dialog'});
        const message = formMessage();
        let selectedMember = null;

        const results = element('div', {className: 'member-link-results'});
        results.addEventListener('mousedown', (mousedownEvent) => mousedownEvent.preventDefault());
        const renderResults = (candidates) => {
            results.replaceChildren(...(candidates.length ? candidates.map((candidate) => {
                const pick = element('button', {
                    className: `secondary-button member-link-candidate${selectedMember && selectedMember.id === candidate.id ? ' is-selected' : ''}`,
                    attributes: {type: 'button'},
                    children: [
                        element('strong', {text: `${candidate.firstName} ${candidate.lastName} (${candidate.memberNumber})`}),
                        element('small', {text: `${candidate.street} • ${new Date(`${candidate.birthDate}T00:00:00`).toLocaleDateString('de-DE')}`}),
                    ],
                });
                pick.addEventListener('click', () => {
                    selectedMember = candidate;
                    save.disabled = false;
                    results.querySelectorAll('.member-link-candidate').forEach((button) => {
                        button.classList.toggle('is-selected', button === pick);
                    });
                });

                return pick;
            }) : [emptyState('Keine Mitglieder gefunden.')]));
        };
        const search = searchField('Name oder Mitgliedsnummer suchen …', '', async (term) => {
            if (term === '') { results.replaceChildren(); return; }
            const data = await request(`/api/admin/v1/event-help-requests/member-candidates?search=${encodeURIComponent(term)}`);
            renderResults(data.items);
        });
        const searchInput = search.querySelector('input');
        searchInput.addEventListener('keydown', (keydownEvent) => {
            if (keydownEvent.key !== 'Enter') return;
            keydownEvent.preventDefault();
            searchInput.dispatchEvent(new Event('change'));
        });

        const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
        const save = element('button', {className: 'button button-compact', text: 'Als Helfer hinzufügen', attributes: {type: 'submit'}});
        save.disabled = true;
        cancel.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => dialog.remove());
        const form = element('form', {className: 'confirm-dialog-content', children: [
            element('p', {className: 'eyebrow', text: 'Helfer hinzufügen'}),
            element('h2', {text: event.eventTitle}),
            search,
            results,
            message,
            element('div', {className: 'confirm-dialog-actions', children: [cancel, save]}),
        ]});
        form.addEventListener('submit', async (submitEvent) => {
            submitEvent.preventDefault();
            if (!selectedMember) return;
            save.disabled = true;
            try {
                await request('/api/admin/v1/event-help-requests', {
                    method: 'POST',
                    body: JSON.stringify({eventIdentifier: event.eventIdentifier, memberId: selectedMember.id}),
                });
                toast(`„${selectedMember.firstName} ${selectedMember.lastName}“ wurde als Helfer hinzugefügt.`);
                dialog.close();
                await showEventManagement();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
                save.disabled = false;
            }
        });
        dialog.append(form);
        document.body.append(dialog);
        dialog.showModal();
    };
    const participationStatus = (requestItem) => {
        if (requestItem.status === 'participated') {
            const hours = new Intl.NumberFormat('de-DE', {maximumFractionDigits: 2}).format(requestItem.participationMinutes / 60);
            return `Teilgenommen · ${hours} Std.`;
        }
        if (requestItem.status === 'not_participated') return 'Nicht teilgenommen';
        if (requestItem.status === 'resolved') return 'Erledigt';
        return 'Neu';
    };
    const groups = new Map();
    data.items.forEach((item) => {
        if (!groups.has(item.eventIdentifier)) groups.set(item.eventIdentifier, {event: item, requests: []});
        groups.get(item.eventIdentifier).requests.push(item);
    });
    // ✓/⊘ zum Erfassen der Teilnahme — je Anmeldung, unabhängig davon, ob sie einzeln oder als
    // Teil einer zusammengeführten Mitgliedskarte angezeigt wird (siehe `buildParticipantEntries`).
    const buildParticipationActionButtons = (requestItem) => {
        const actionButtons = [];
        if (!canEditModule('event_helpers') || requestItem.status === 'resolved') return actionButtons;
        const participated = element('button', {
            className: 'participant-icon-button participant-icon-button-confirm',
            text: requestItem.status === 'participated' ? '✎' : '✓',
            attributes: {
                type: 'button',
                title: requestItem.status === 'participated' ? 'Teilnahmezeiten bearbeiten' : 'Als teilgenommen markieren',
                'aria-label': requestItem.status === 'participated' ? 'Teilnahmezeiten bearbeiten' : 'Als teilgenommen markieren',
            },
        });
        participated.addEventListener('click', () => openParticipationDialog(requestItem));
        actionButtons.push(participated);
        if (requestItem.status !== 'not_participated') {
            const absent = element('button', {
                className: 'participant-icon-button participant-icon-button-absent',
                text: '⊘',
                attributes: {type: 'button', title: 'Als nicht teilgenommen markieren', 'aria-label': 'Als nicht teilgenommen markieren'},
            });
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
        return actionButtons;
    };
    // Nachricht, gewählte Aktivitäten und Hilfezeiträume je Anmeldung, aufklappbar.
    const buildParticipantDetails = (requestItem, {includeSubmittedAt = false} = {}) => {
        const intervals = Array.isArray(requestItem.participationIntervals) ? requestItem.participationIntervals : [];
        const selectedActivities = Array.isArray(requestItem.selectedActivities) ? requestItem.selectedActivities : [];
        const hasMessage = typeof requestItem.message === 'string' && requestItem.message.trim() !== '';
        const detailsId = `event-helper-details-${requestItem.id}`;
        const detailsBody = (hasMessage || selectedActivities.length > 0 || intervals.length > 0 || includeSubmittedAt)
            ? element('div', {className: 'event-helper-participant-details', attributes: {id: detailsId, hidden: 'hidden'}, children: [
                ...(includeSubmittedAt ? [element('small', {text: `Angemeldet am ${new Date(requestItem.submittedAt).toLocaleString('de-DE')}`})] : []),
                ...(hasMessage ? [element('p', {className: 'event-helper-participant-message', text: requestItem.message})] : []),
                ...((selectedActivities.length || intervals.length) ? [element('div', {className: 'event-helper-participant-meta', children: [
                    ...(selectedActivities.length ? [element('div', {className: 'selected-activity-list', children: [
                        element('strong', {text: 'Aktivitäten'}),
                        ...selectedActivities.map((activity) => element('span', {className: 'activity-chip', text: activity.name})),
                    ]})] : []),
                    ...(intervals.length ? [element('div', {className: 'participation-times', children: [
                        element('strong', {text: 'Zeiten'}),
                        element('ul', {className: 'participation-interval-summary', children: intervals.map((interval) => element('li', {text: `${interval.fromTime}–${interval.toTime} Uhr`}))}),
                    ]})] : []),
                ]})] : []),
            ]})
            : null;
        return {detailsBody, detailsId};
    };
    // Macht `toggle` (Name- bzw. Zusammenfassungs-Button) zum Aufklapper für `detailsBody`,
    // falls vorhanden — sonst bleibt `toggle` ein reines Anzeige-Element ohne Verhalten.
    const wireDetailsToggle = (toggle, detailsBody, detailsId) => {
        if (!detailsBody) return toggle;
        const button = element('button', {
            className: 'event-helper-participant-toggle',
            attributes: {type: 'button', 'aria-expanded': 'false', 'aria-controls': detailsId},
            children: [toggle],
        });
        button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!expanded));
            detailsBody.hidden = expanded;
        });
        return button;
    };
    // Die Bubble übernimmt beides: zeigt die Mitgliedsnummer (falls verknüpft) und dient
    // zugleich als Verknüpfen-/Bearbeiten-Button — ein eigener zusätzlicher Button daneben
    // entfällt damit. `items` sind alle Anmeldungen, auf die eine Änderung der Verknüpfung
    // angewendet werden soll (bei zusammengeführten Mehrfachanmeldungen mehr als eine, siehe
    // `buildParticipantEntries`). Verknüpfen (bzw. eine bestehende — ggf. falsche — Verknüpfung
    // nachträglich bearbeiten) muss unabhängig vom Teilnahmestatus möglich sein, da die
    // Arbeitszeit-Erstattung zum Jahresende die Zuordnung zum Mitglied braucht.
    const buildMemberBubble = (items) => {
        const primary = items[0];
        const isLinked = !!primary.memberNumber;
        if (!canEditModule('event_helpers')) {
            // Ohne Bearbeitungsrecht nur eine reine Anzeige, keine leere Bubble ohne Verknüpfung.
            return isLinked ? element('span', {className: 'status-badge member-link-badge is-linked', text: primary.memberNumber}) : null;
        }
        const button = element('button', {
            className: `status-badge member-link-badge${isLinked ? ' is-linked' : ''}`,
            attributes: {
                type: 'button',
                title: isLinked ? 'Mitgliedsverknüpfung bearbeiten' : 'Mitglied verknüpfen',
                'aria-label': isLinked ? 'Mitgliedsverknüpfung bearbeiten' : 'Mitglied verknüpfen',
            },
        });
        if (isLinked) {
            button.textContent = primary.memberNumber;
        } else {
            const icon = element('span', {className: 'member-link-badge-icon', attributes: {'aria-hidden': 'true'}});
            // Schlichtes Kettenglied-Icon statt Emoji — Vorbild: `buildMemberAccessNav`.
            icon.innerHTML = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>';
            button.append(icon, element('span', {text: 'Verknüpfen'}));
        }
        button.addEventListener('click', () => openLinkMemberDialog(items));

        return button;
    };
    // Straße/Hausnummer, Geburtsdatum und die tatsächlich für den Mailversand verwendete(n)
    // E-Mail-Adresse(n) des verknüpften Mitglieds unter dem Namen — analog zur Kandidatenanzeige
    // beim manuellen Verknüpfen (siehe `openLinkMemberDialog`). Hat das Mitglied selbst keine
    // Adresse hinterlegt, zeigt `recipientEmails` (siehe `EventHelpRequestRecipientResolver`)
    // stattdessen die des ganzen Haushalts — genau die, die auch „Mail an alle Helfer"/„Mail an
    // diesen Helfer" erreichen.
    const buildMemberDetailLines = (primary) => {
        if (!primary.memberNumber) return [];
        const lines = [];
        const parts = [];
        if (primary.memberStreet) parts.push(primary.memberStreet);
        if (primary.memberBirthDate) parts.push(new Date(`${primary.memberBirthDate}T00:00:00`).toLocaleDateString('de-DE'));
        if (parts.length) lines.push(element('small', {className: 'event-helper-member-detail', text: parts.join(' • ')}));
        if (Array.isArray(primary.recipientEmails) && primary.recipientEmails.length) {
            lines.push(element('small', {className: 'event-helper-member-detail', text: primary.recipientEmails.join(', ')}));
        }

        return lines;
    };
    const renderSingleParticipant = (requestItem, event) => {
        // Reihenfolge der Icon-Buttons ganz rechts: Teilgenommen markieren, Nicht teilgenommen,
        // Mail — Mail deshalb angehängt statt (wie die anderen beiden) über den Teilnahmestatus
        // ausgeblendet, da sie unabhängig davon verfügbar bleiben soll.
        const mailButton = buildMailButton(event, [requestItem], `${requestItem.firstName} ${requestItem.lastName}`);
        const actionButtons = [...buildParticipationActionButtons(requestItem), ...(mailButton ? [mailButton] : [])];
        // "Angemeldet am" steht nicht mehr dauerhaft sichtbar in der Kopfzeile, sondern nur noch
        // in den aufklappbaren Details — dort, wo sie für die Verwaltung tatsächlich gebraucht wird.
        const {detailsBody, detailsId} = buildParticipantDetails(requestItem, {includeSubmittedAt: true});
        const identity = element('div', {className: 'event-helper-participant-identity', children: [
            element('div', {className: 'event-helper-participant-name-row', children: [
                element('strong', {text: `${requestItem.firstName} ${requestItem.lastName}`}),
                ...(() => { const bubble = buildMemberBubble([requestItem]); return bubble ? [bubble] : []; })(),
            ]}),
            ...buildMemberDetailLines(requestItem),
        ]});
        return element('article', {className: `event-helper-participant status-${requestItem.status}`, children: [
            element('header', {className: 'event-helper-participant-header', children: [
                wireDetailsToggle(identity, detailsBody, detailsId),
                element('div', {className: 'event-helper-participant-side', children: [
                    element('span', {className: `participant-status status-${requestItem.status}`, text: participationStatus(requestItem)}),
                    ...(actionButtons.length ? [element('div', {className: 'participant-actions', children: actionButtons})] : []),
                ]}),
            ]}),
            ...(detailsBody ? [detailsBody] : []),
        ]});
    };
    // Mehrere Anmeldungen derselben Person zur selben Veranstaltung (siehe
    // `buildParticipantEntries`) — eine gemeinsame Kopfzeile mit Name/Mitgliedsbubble, darunter
    // jede ursprüngliche Anmeldung als eigene Zeile mit ihren eigenen Teilnahme-Aktionen, damit
    // dabei nichts von der bisherigen Einzel-Erfassung verloren geht.
    const renderMergedParticipant = (items, event) => {
        const primary = items[0];
        const name = primary.memberFirstName && primary.memberLastName
            ? `${primary.memberFirstName} ${primary.memberLastName}`
            : `${primary.firstName} ${primary.lastName}`;
        const bubble = buildMemberBubble(items);
        const identity = element('div', {className: 'event-helper-participant-identity', children: [
            element('div', {className: 'event-helper-participant-name-row', children: [
                element('strong', {text: name}),
                ...(bubble ? [bubble] : []),
            ]}),
            ...buildMemberDetailLines(primary),
            element('small', {text: `${items.length} Anmeldungen zu dieser Veranstaltung zusammengeführt`}),
        ]});
        // Die Mail gilt der ganzen Person (alle zusammengeführten Anmeldungen, siehe
        // `buildMailButton`), nicht nur einer einzelnen Zeile — Icon deshalb nur einmal, ganz
        // rechts in der ersten Zeile, statt in jeder Zeile wiederholt.
        const mailButton = buildMailButton(event, items, name);
        const rows = items.map((requestItem, index) => {
            const actionButtons = [
                ...buildParticipationActionButtons(requestItem),
                ...(index === 0 && mailButton ? [mailButton] : []),
            ];
            const {detailsBody, detailsId} = buildParticipantDetails(requestItem);
            const summary = element('div', {className: 'event-helper-merged-row-summary', children: [
                element('small', {text: `Angemeldet am ${new Date(requestItem.submittedAt).toLocaleString('de-DE')}`}),
                element('span', {className: `participant-status status-${requestItem.status}`, text: participationStatus(requestItem)}),
            ]});
            return element('div', {className: `event-helper-merged-row status-${requestItem.status}`, children: [
                element('div', {className: 'event-helper-merged-row-header', children: [
                    wireDetailsToggle(summary, detailsBody, detailsId),
                    ...(actionButtons.length ? [element('div', {className: 'participant-actions', children: actionButtons})] : []),
                ]}),
                ...(detailsBody ? [detailsBody] : []),
            ]});
        });
        return element('article', {className: 'event-helper-participant event-helper-participant-merged', children: [
            element('header', {className: 'event-helper-participant-header', children: [identity]}),
            element('div', {className: 'event-helper-merged-list', children: rows}),
        ]});
    };
    // Fasst mehrere Anmeldungen derselben Veranstaltung zu einer Karte zusammen, wenn sie mit
    // demselben Mitglied verknüpft sind (z. B. weil sich jemand aus Versehen zweimal angemeldet
    // hat) — die ursprüngliche Reihenfolge bleibt dabei anhand des ersten Vorkommens erhalten.
    const buildParticipantEntries = (requestList, event) => {
        const byMemberId = new Map();
        requestList.forEach((requestItem) => {
            if (!requestItem.memberId) return;
            if (!byMemberId.has(requestItem.memberId)) byMemberId.set(requestItem.memberId, []);
            byMemberId.get(requestItem.memberId).push(requestItem);
        });
        const rendered = new Set();
        const entries = [];
        requestList.forEach((requestItem) => {
            if (rendered.has(requestItem.id)) return;
            const group = requestItem.memberId ? byMemberId.get(requestItem.memberId) : [requestItem];
            group.forEach((groupItem) => rendered.add(groupItem.id));
            entries.push(group.length > 1 ? renderMergedParticipant(group, event) : renderSingleParticipant(requestItem, event));
        });
        return entries;
    };
    // Freie Rundmail (Betreff/Text selbst getippt, keine Mailvorlage) an alle Helfer dieser
    // Veranstaltung — z. B. für den Treffpunkt, sonstige wichtige Hinweise oder als Erinnerung
    // kurz vorher (siehe `SendEventHelpRequestBroadcastUseCase`). Ohne `requestIds` gilt das für
    // alle Helfer der Veranstaltung (Button „Mail an alle Helfer"); mit `requestIds` (siehe
    // `buildMailButton`) nur für die betreffende(n) Anmeldung(en) — dasselbe Overlay für beide
    // Fälle, nur mit angepasster Überschrift und ohne den Betreff-Zusatz für den Namen.
    //
    // Das „An:"-Feld startet mit den automatisch ermittelten Empfängern (siehe
    // `GetEventHelpRequestBroadcastRecipientsUseCase`), lässt sich dort aber noch anpassen
    // (Adresse entfernen bzw. über das Eingabefeld + Vorschlagsliste ergänzen) — verschickt wird
    // beim Senden genau die dann dort stehende Liste, nicht mehr automatisch ermittelt.
    const openEventHelpBroadcastDialog = async (event, {requestIds = null, recipientName = null} = {}) => {
        const query = new URLSearchParams({eventIdentifier: event.eventIdentifier});
        (requestIds || []).forEach((id) => query.append('requestIds[]', id));
        let recipientsData;
        try {
            recipientsData = await request(`/api/admin/v1/event-help-requests/broadcast-recipients?${query.toString()}`);
        } catch (error) {
            toast(error.message, 'error');
            return;
        }
        let recipients = [...recipientsData.defaultEmails];
        const suggestions = recipientsData.suggestedEmails;

        const dialog = element('dialog', {className: 'confirm-dialog'});
        const message = formMessage();
        const subjectField = field('Betreff', 'subject', `Helfermitteilung - ${event.eventTitle}`);
        const bodyField = field('Text', 'body', '', 'textarea');
        const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
        const submit = element('button', {className: 'button button-compact', text: 'Senden', attributes: {type: 'submit'}});
        cancel.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => dialog.remove());

        // "An:"-Feld: entfernbare Chips je Empfänger, darunter ein Eingabefeld (mit Pulldown aus
        // `suggestedEmails`, z. B. weitere Haushaltsmitglieder) zum händischen Nachtragen.
        const recipientList = element('div', {className: 'recipient-chip-list'});
        const datalistId = `broadcast-recipient-suggestions-${Math.random().toString(36).slice(2)}`;
        const datalist = element('datalist', {attributes: {id: datalistId}});
        const updateDatalist = () => {
            datalist.replaceChildren(...suggestions
                .filter((suggestedEmail) => !recipients.some((existing) => existing.toLowerCase() === suggestedEmail.toLowerCase()))
                .map((suggestedEmail) => element('option', {attributes: {value: suggestedEmail}})));
        };
        const renderRecipients = () => {
            recipientList.replaceChildren(...(recipients.length ? recipients.map((recipientEmail) => {
                const remove = element('button', {className: 'recipient-chip-remove', text: '×', attributes: {type: 'button', 'aria-label': `${recipientEmail} entfernen`}});
                remove.addEventListener('click', () => {
                    recipients = recipients.filter((existing) => existing !== recipientEmail);
                    renderRecipients();
                    updateDatalist();
                });
                return element('span', {className: 'recipient-chip', children: [element('span', {text: recipientEmail}), remove]});
            }) : [element('span', {className: 'recipient-chip-empty', text: 'Keine Empfänger — bitte mindestens eine E-Mail-Adresse hinzufügen.'})]));
            updateDatalist();
        };
        renderRecipients();
        const addInput = element('input', {attributes: {type: 'email', placeholder: 'E-Mail-Adresse hinzufügen …', list: datalistId, 'aria-label': 'E-Mail-Adresse hinzufügen'}});
        const addRecipient = () => {
            const newRecipientEmail = addInput.value.trim();
            if (newRecipientEmail === '') return;
            if (!recipients.some((existing) => existing.toLowerCase() === newRecipientEmail.toLowerCase())) {
                recipients = [...recipients, newRecipientEmail];
                renderRecipients();
            }
            addInput.value = '';
            addInput.focus();
        };
        const addButton = element('button', {className: 'secondary-button button-compact', text: '+ Hinzufügen', attributes: {type: 'button'}});
        addButton.addEventListener('click', addRecipient);
        addInput.addEventListener('keydown', (keydownEvent) => {
            if (keydownEvent.key !== 'Enter') return;
            keydownEvent.preventDefault();
            addRecipient();
        });
        const recipientsField = element('div', {className: 'field recipient-field', children: [
            element('span', {text: 'An'}),
            recipientList,
            element('div', {className: 'recipient-add-row', children: [addInput, addButton, datalist]}),
        ]});

        const form = element('form', {className: 'confirm-dialog-content', children: [
            element('p', {className: 'eyebrow', text: recipientName ? 'Mail an Helfer' : 'Mail an alle Helfer'}),
            element('h2', {text: recipientName || event.eventTitle}),
            ...(recipientName ? [element('p', {className: 'field-hint', text: event.eventTitle})] : []),
            recipientsField,
            subjectField,
            bodyField,
            message,
            element('div', {className: 'confirm-dialog-actions', children: [cancel, submit]}),
        ]});
        subjectField.querySelector('input').required = true;
        bodyField.querySelector('textarea').required = true;
        form.addEventListener('submit', async (submitEvent) => {
            submitEvent.preventDefault();
            if (recipients.length === 0) {
                toast('Mindestens eine Empfänger-E-Mail-Adresse ist erforderlich.', 'error');
                return;
            }
            submit.disabled = true;
            try {
                const data = new FormData(form);
                const result = await request('/api/admin/v1/event-help-requests/broadcast', {method: 'POST', body: JSON.stringify({
                    subject: data.get('subject'),
                    body: data.get('body'),
                    recipients,
                })});
                dialog.close();
                toast(`Der Versand an ${result.recipientCount} ${result.recipientCount === 1 ? 'Person wurde' : 'Personen wurde'} im Hintergrund gestartet.`, 'success');
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
                submit.disabled = false;
            }
        });
        dialog.append(form);
        document.body.append(dialog);
        dialog.showModal();
        // Sonst setzt der Browser den Cursor automatisch ins erste Feld — der vorbefüllte
        // Betreff würde damit ungewollt teilweise markiert/überschrieben.
        cancel.focus();
    };
    // Icon-Button (✉) neben dem Namen einer einzelnen Anmeldung/Person — öffnet dasselbe
    // Overlay wie „Mail an alle Helfer", aber auf diese Anmeldung(en) beschränkt (bei
    // zusammengeführten Mehrfachanmeldungen `items` mehr als eine, siehe `buildMemberBubble`).
    const buildMailButton = (event, items, recipientName) => {
        if (!canEditModule('event_helpers')) return null;
        const button = element('button', {
            className: 'participant-icon-button participant-icon-button-mail',
            text: '✉',
            attributes: {type: 'button', title: 'Mail an diesen Helfer', 'aria-label': 'Mail an diesen Helfer'},
        });
        button.addEventListener('click', () => openEventHelpBroadcastDialog(event, {
            requestIds: items.map((item) => item.id),
            recipientName,
        }));

        return button;
    };
    const renderEventHelperGroup = ({event, requests}) => {
        const participatedCount = requests.filter((requestItem) => requestItem.status === 'participated').length;
        // "Nicht teilgenommen" steht nicht mehr in der normalen Liste, sondern gesammelt in einem
        // eigenen, eingeklappten Accordion am Ende der Veranstaltung (siehe unten) — oben sind
        // nur noch die Status „Neu" und „Teilgenommen" zu sehen.
        const notParticipatedRequests = requests.filter((requestItem) => requestItem.status === 'not_participated');
        const visibleRequests = requests.filter((requestItem) => requestItem.status !== 'not_participated');
        const broadcastButton = element('button', {className: 'secondary-button button-compact', text: 'Mail an alle Helfer', attributes: {type: 'button'}});
        broadcastButton.addEventListener('click', () => openEventHelpBroadcastDialog(event));
        const addHelperButton = element('button', {
            className: 'secondary-button button-compact',
            text: '＋',
            attributes: {type: 'button', title: 'Mitglied als Helfer hinzufügen', 'aria-label': 'Mitglied als Helfer hinzufügen'},
        });
        addHelperButton.addEventListener('click', () => openAddEventHelperDialog(event));
        const visibleEntries = buildParticipantEntries(visibleRequests, event);
        const notParticipatedKey = `not-participated:${event.eventIdentifier}`;
        const notParticipatedAccordion = notParticipatedRequests.length ? element('details', {
            className: 'event-helper-archive event-helper-not-participated',
            attributes: {
                'data-archive-key': notParticipatedKey,
                ...(previouslyOpenArchiveKeys.has(notParticipatedKey) ? {open: 'open'} : {}),
            },
            children: [
                element('summary', {children: [
                    element('strong', {text: 'Nicht teilgenommen'}),
                    element('span', {className: 'status-badge', text: String(notParticipatedRequests.length)}),
                ]}),
                element('div', {className: 'event-helper-archive-list event-helper-list', children: buildParticipantEntries(notParticipatedRequests, event)}),
            ],
        }) : null;
        return element('section', {className: 'event-helper-group', children: [
        element('header', {children: [
            element('div', {children: [
                element('h3', {text: event.eventTitle}),
                element('p', {text: `${new Date(`${event.eventDate}T00:00:00`).toLocaleDateString('de-DE')} · ${event.eventTime} Uhr`}),
            ]}),
            element('div', {className: 'event-helper-header-actions', children: [
                ...(canEditModule('event_helpers')
                    ? [element('div', {className: 'event-helper-header-buttons', children: [broadcastButton, addHelperButton]})]
                    : []),
                element('div', {className: 'event-helper-counts', children: [
                    element('small', {text: `${requests.length} ${requests.length === 1 ? 'Person war' : 'Personen waren'} angemeldet`}),
                    element('span', {className: 'status-badge', text: `${participatedCount} ${participatedCount === 1 ? 'Helfer' : 'Helfer'}`}),
                ]}),
            ]}),
        ]}),
        element('div', {className: 'event-helper-list', children: visibleEntries.length ? visibleEntries : [emptyState('Keine offenen Helfer.')]}),
        ...(notParticipatedAccordion ? [notParticipatedAccordion] : []),
        ]});
    };
    const {
        currentYear, todayItems: todayGroups, upcomingItems: upcomingGroups,
        completedCurrentYearItems: completedCurrentYearGroups, archiveByYear: archiveGroups,
    } = bucketItemsByDate([...groups.values()], (group) => group.event.eventDate, (group) => group.event.eventTime);
    const eventSection = (title, eventGroups, modifier = '') => element('section', {
        className: `event-helper-section ${modifier}`.trim(),
        children: [
            element('div', {className: 'event-helper-section-heading', children: [
                element('h3', {text: title}),
                element('span', {className: 'status-badge', text: String(eventGroups.length)}),
            ]}),
            element('div', {className: 'event-helper-section-list', children: eventGroups.map(renderEventHelperGroup)}),
        ],
    });
    const eventArchive = (title, eventGroups, key) => element('details', {
        className: 'event-helper-archive',
        attributes: {
            'data-archive-key': key,
            ...(previouslyOpenArchiveKeys.has(key) ? {open: 'open'} : {}),
        },
        children: [
            element('summary', {children: [
                element('strong', {text: title}),
                element('span', {className: 'status-badge', text: String(eventGroups.length)}),
            ]}),
            element('div', {className: 'event-helper-archive-list', children: eventGroups.map(renderEventHelperGroup)}),
        ],
    });
    const sections = [
        ...(todayGroups.length ? [eventSection('Heute', todayGroups, 'event-helper-section-today')] : []),
        ...(upcomingGroups.length ? [eventSection('Kommende Veranstaltungen', upcomingGroups)] : []),
        ...(completedCurrentYearGroups.length
            ? [eventArchive(`Abgeschlossene Veranstaltungen ${currentYear}`, completedCurrentYearGroups, `current-year:${currentYear}`)]
            : []),
        ...[...archiveGroups.entries()]
            .sort(([firstYear], [secondYear]) => secondYear - firstYear)
            .map(([year, eventGroups]) => eventArchive(`Archiv ${year}`, eventGroups, `year:${year}`)),
    ];
    workspace.replaceChildren(
        sectionHeading('Veranstaltungshelfer', 'Anmeldungen nach Veranstaltung gruppiert verwalten'),
        element('div', {className: 'event-helper-groups', children: sections.length ? sections : [emptyState('Noch keine Helferanmeldungen vorhanden.')]}),
    );
};

const openActivityDialog = (activity, onSaved) => {
    const dialog = element('dialog', {className: 'activity-dialog'});
    const name = field('Bezeichnung', `activity-name-${activity?.id || 'new'}`, activity?.name || '');
    const description = field('Beschreibung (optional)', `activity-description-${activity?.id || 'new'}`, activity?.description || '', 'textarea');
    const defaultRequiredHelpers = field(
        'Standard-Anzahl benötigter Helfer (optional)',
        `activity-default-required-helpers-${activity?.id || 'new'}`,
        activity?.defaultRequiredHelpers ?? '',
        'number',
    );
    const defaultRequiredHelpersInput = defaultRequiredHelpers.querySelector('input');
    defaultRequiredHelpersInput.min = '1';
    defaultRequiredHelpersInput.max = '999';
    const active = element('input', {attributes: {type: 'checkbox'}});
    active.checked = activity?.active !== false;
    const alwaysIncluded = element('input', {attributes: {type: 'checkbox'}});
    alwaysIncluded.checked = activity?.alwaysIncluded === true;
    const message = formMessage();
    const submit = element('button', {className: 'button', text: activity ? 'Änderungen speichern' : 'Aktivität anlegen', attributes: {type: 'submit'}});
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
    const actions = [cancel, submit];
    if (activity) {
        const deleteButton = element('button', {className: 'button danger-button', text: 'Löschen', attributes: {type: 'button'}});
        deleteButton.addEventListener('click', async () => {
            const confirmed = await confirmAction(
                `„${activity.name}“ löschen?`,
                'Die Aktivität wird endgültig entfernt und dabei aus allen Veranstaltungen, Arbeitseinsätzen und Vorlagen entfernt, die sie zuordnen. Das kann nicht rückgängig gemacht werden.',
                'Löschen',
            );
            if (!confirmed) return;
            try {
                await request(`/api/admin/v1/event-activities/${activity.id}`, {method: 'DELETE'});
                toast('Aktivität wurde gelöscht.');
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
            element('div', {children: [
                element('p', {className: 'eyebrow', text: activity ? 'Aktivität bearbeiten' : 'Neue Aktivität'}),
                element('h2', {text: activity?.name || 'Aktivität anlegen'}),
            ]}),
        ]}),
        name,
        description,
        defaultRequiredHelpers,
        element('small', {text: 'Wird beim Zuordnen der Aktivität zu einer Veranstaltung als Vorschlag für die benötigte Helferzahl übernommen.'}),
        element('label', {className: 'check-field', children: [active, element('span', {text: 'Aktivität ist auswählbar'})]}),
        element('label', {className: 'check-field', children: [alwaysIncluded, element('span', {text: 'Immer in neue Veranstaltungen einbinden'})]}),
        element('small', {text: 'Wird beim Anlegen einer neuen Veranstaltung automatisch zugeordnet.'}),
        message,
        element('div', {className: 'confirm-dialog-actions', children: actions}),
    ]});
    name.querySelector('input').required = true;
    cancel.addEventListener('click', () => dialog.close());
    close.addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        try {
            await request(activity ? `/api/admin/v1/event-activities/${activity.id}` : '/api/admin/v1/event-activities', {
                method: activity ? 'PUT' : 'POST',
                body: JSON.stringify({
                    name: name.querySelector('input').value,
                    description: description.querySelector('textarea').value,
                    active: active.checked,
                    defaultRequiredHelpers: defaultRequiredHelpersInput.value === '' ? null : Number.parseInt(defaultRequiredHelpersInput.value, 10),
                    alwaysIncluded: alwaysIncluded.checked,
                }),
            });
            toast(activity ? 'Aktivität wurde gespeichert.' : 'Aktivität wurde angelegt.');
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

const showActivities = async () => {
    const data = await request('/api/admin/v1/event-activities');
    const heading = sectionHeading('Aktivitäten', 'Wiederverwendbare Tätigkeiten für Veranstaltungen und gemeinsame Arbeitseinsätze');
    if (canEditModule('activities')) {
        const create = element('button', {className: 'button', text: '＋ Neue Aktivität', attributes: {type: 'button'}});
        create.addEventListener('click', () => openActivityDialog(null, showEventManagement));
        heading.append(create);
    }
    const rows = data.items.map((activity) => {
        const row = element('button', {
            className: `activity-list-row${activity.active ? '' : ' is-inactive'}`,
            attributes: {type: 'button', ...(canEditModule('activities') ? {} : {disabled: 'disabled'})},
            children: [
                element('span', {className: 'activity-list-copy', children: [
                    element('strong', {text: activity.name}),
                    element('small', {text: activity.description || 'Keine Beschreibung hinterlegt.'}),
                    ...(activity.defaultRequiredHelpers ? [element('small', {text: `Standard: ${activity.defaultRequiredHelpers} Helfer`})] : []),
                ]}),
                ...(activity.alwaysIncluded ? [element('span', {className: 'status-badge', text: 'Immer eingebunden'})] : []),
                element('span', {className: `status-badge ${activity.active ? 'status-active' : 'status-inactive'}`, text: activity.active ? 'Aktiv' : 'Inaktiv'}),
                ...(canEditModule('activities') ? [element('span', {className: 'activity-list-edit', text: 'Bearbeiten ›'})] : []),
            ],
        });
        if (canEditModule('activities')) row.addEventListener('click', () => openActivityDialog(activity, showEventManagement));
        return row;
    });
    workspace.replaceChildren(
        heading,
        element('div', {className: 'activity-list', children: data.items.length
            ? rows
            : [emptyState('Noch keine Aktivitäten angelegt.')]}),
    );
};

/**
 * Aktivitäts-Zuordnungs-Editor (Aktivität, Anzahl, Start/Ende, Treffort, Bemerkung je Zuordnung,
 * inkl. „Zuordnen"/„Neue Aktivität anlegen") — gemeinsam genutzt von `openEventDialog` und
 * `openEventTemplateDialog`. `activities` wird in place mutiert (push/splice), die aufrufende
 * Stelle behält dieselbe Referenz für Validierung (`list`, siehe dort die Zählfeld-Prüfung beim
 * Absenden) und die spätere Nutzlast.
 */
const buildActivityAssignmentEditor = (activities, handlers, dialogKey, message) => {
    let activityCatalog = (handlers.activities || []).slice();
    const activityList = element('div', {className: 'event-activity-editor-list'});
    const renderActivityRows = () => {
        activityList.replaceChildren(...activities.map((assignment, assignmentIndex) => {
            const fieldId = (name) => `event-activity-${name}-${dialogKey}-${assignmentIndex}`;
            const activityField = (label, name, control, modifier = '') => {
                const id = fieldId(name);
                control.id = id;
                return element('label', {
                    className: `event-activity-field event-activity-field-${name}${modifier ? ` ${modifier}` : ''}`,
                    attributes: {for: id},
                    children: [element('span', {text: label}), control],
                });
            };
            const select = element('select');
            activityCatalog.forEach((activity) => {
                if (!activity.active && activity.id !== assignment.activityId) return;
                if (activity.id !== assignment.activityId && activities.some((item) => item.activityId === activity.id)) return;
                select.append(element('option', {text: `${activity.name}${activity.active ? '' : ' (inaktiv)'}`, attributes: {value: activity.id}}));
            });
            select.value = assignment.activityId;
            select.addEventListener('change', () => {
                assignment.activityId = select.value;
                const selectedActivity = activityCatalog.find((activity) => activity.id === select.value);
                if (selectedActivity?.defaultRequiredHelpers) {
                    assignment.requiredHelpers = String(selectedActivity.defaultRequiredHelpers);
                    count.value = String(selectedActivity.defaultRequiredHelpers);
                }
            });
            const count = element('input', {className: 'event-activity-detail-input', attributes: {
                type: 'number', inputmode: 'numeric', value: String(assignment.requiredHelpers ?? 1),
                'aria-label': 'Anzahl benötigter Helfer',
            }});
            count.addEventListener('input', () => {
                assignment.requiredHelpers = count.value;
                if (count.hasAttribute('aria-invalid')) message.textContent = '';
                count.setCustomValidity('');
                count.removeAttribute('aria-invalid');
            });
            const timeInput = element('input', {className: 'event-activity-detail-input', attributes: {type: 'time', 'aria-label': 'Start'}});
            timeInput.value = assignment.time || '';
            timeInput.addEventListener('input', () => assignment.time = timeInput.value || null);
            const meetTimeInput = element('input', {className: 'event-activity-detail-input', attributes: {type: 'time', 'aria-label': 'Ende'}});
            meetTimeInput.value = assignment.meetTime || '';
            meetTimeInput.addEventListener('input', () => assignment.meetTime = meetTimeInput.value || null);
            const meetPlaceInput = element('input', {className: 'event-activity-detail-input', attributes: {type: 'text', maxlength: '160', 'aria-label': 'Treffort (optional)'}});
            meetPlaceInput.value = assignment.meetPlace || '';
            meetPlaceInput.addEventListener('input', () => assignment.meetPlace = meetPlaceInput.value || null);
            const remarkInput = element('input', {className: 'event-activity-detail-input', attributes: {type: 'text', maxlength: '500', 'aria-label': 'Bemerkung (optional)'}});
            remarkInput.value = assignment.remark || '';
            remarkInput.addEventListener('input', () => assignment.remark = remarkInput.value || null);
            assignment.remarkExpanded ??= false;
            const remarkToggleLabel = assignment.remarkExpanded
                ? 'Bemerkungsfeld ausblenden'
                : (assignment.remark ? 'Bemerkung bearbeiten' : 'Bemerkung hinzufügen');
            const remarkToggle = element('button', {
                className: `secondary-button event-activity-remark-toggle${assignment.remark ? ' has-value' : ''}`,
                attributes: {
                    type: 'button',
                    title: remarkToggleLabel,
                    'aria-label': remarkToggleLabel,
                    'aria-expanded': String(assignment.remarkExpanded),
                    ...(assignment.remarkExpanded ? {'aria-controls': fieldId('remark')} : {}),
                },
                children: [
                    element('span', {className: 'event-activity-remark-toggle-icon', text: assignment.remarkExpanded ? '−' : '＋', attributes: {'aria-hidden': 'true'}}),
                    element('span', {className: 'event-activity-remark-toggle-label', text: 'Bemerkung'}),
                ],
            });
            remarkToggle.addEventListener('click', () => {
                assignment.remarkExpanded = !assignment.remarkExpanded;
                renderActivityRows();
                if (assignment.remarkExpanded) activityList.querySelector(`#${CSS.escape(fieldId('remark'))}`)?.focus();
            });
            const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', title: 'Zuordnung entfernen', 'aria-label': 'Zuordnung entfernen'}});
            remove.addEventListener('click', () => {
                activities.splice(assignmentIndex, 1);
                renderActivityRows();
            });

            return element('div', {className: 'event-schedule-activity-row', children: [
                activityField('Aktivität', 'activity', select),
                activityField('Anzahl', 'count', count),
                activityField('Start', 'start', timeInput),
                activityField('Ende', 'end', meetTimeInput),
                activityField('Treffort (optional)', 'meet-place', meetPlaceInput),
                remarkToggle,
                remove,
                ...(assignment.remarkExpanded ? [activityField('Bemerkung (optional)', 'remark', remarkInput)] : []),
            ]});
        }));
    };
    const addActivity = element('button', {className: 'secondary-button', text: '＋ Aktivität zuordnen', attributes: {type: 'button'}});
    addActivity.addEventListener('click', () => {
        const available = activityCatalog.find((activity) => activity.active && !activities.some((item) => item.activityId === activity.id));
        if (!available) {
            toast('Keine weitere aktive Aktivität verfügbar.', 'error');
            return;
        }
        activities.push({activityId: available.id, requiredHelpers: String(available.defaultRequiredHelpers ?? 1), time: null, meetTime: null, meetPlace: null, remark: null});
        renderActivityRows();
    });
    const addNewActivity = element('button', {className: 'secondary-button', text: '＋ Neue Aktivität anlegen', attributes: {type: 'button'}});
    addNewActivity.addEventListener('click', () => {
        const knownIds = new Set(activityCatalog.map((activity) => activity.id));
        openActivityDialog(null, async () => {
            const refreshed = await request('/api/admin/v1/event-activities');
            activityCatalog = refreshed.items;
            handlers.activities = refreshed.items;
            const createdActivity = activityCatalog.find((activity) => !knownIds.has(activity.id));
            if (createdActivity) {
                activities.push({activityId: createdActivity.id, requiredHelpers: String(createdActivity.defaultRequiredHelpers ?? 1), time: null, meetTime: null, meetPlace: null, remark: null});
            }
            toast('Aktivität wurde angelegt und zugeordnet.');
            renderActivityRows();
        });
    });
    renderActivityRows();
    const editor = element('fieldset', {className: 'event-activity-editor', children: [
        element('legend', {text: 'Aktivitäten für die Helferanmeldung'}),
        element('small', {text: 'Start, Ende, Treffort und Bemerkung werden Helfern beim Anmelden angezeigt.'}),
        activityList,
        element('div', {className: 'event-activity-editor-actions', children: [addActivity, addNewActivity]}),
    ]});

    return {editor, list: activityList};
};

/**
 * Aktionsbutton-Editor (Beschriftung + Ziel-URL/-Seite, mehrere möglich) — gemeinsam genutzt von
 * `openEventDialog` und `openEventTemplateDialog`. `callToActions` wird in place mutiert.
 */
/**
 * Editor für Aktionsbuttons (Beschriftung + URL oder CMS-Seite) — genutzt von Veranstaltungen,
 * Vorlagen und dem Modul „Fotos“ (`admin/photos.js`). `callToActions` wird in place gepflegt.
 */
const buildCallToActionEditor = (callToActions, pages, dialogKey, options = {}) => {
    const {
        legend = 'Weitere Aktionsbuttons',
        hint = 'Optional können weitere Buttons auf eine URL oder eine CMS-Seite verweisen.',
        defaultLabel = 'Mehr erfahren',
    } = options;
    const actionList = element('div', {className: 'event-call-action-editor-list'});
    const renderActions = () => {
        actionList.replaceChildren(...callToActions.map((action, actionIndex) => {
            const label = field('Button-Beschriftung', `event-action-label-${dialogKey}-${actionIndex}`, action.label || defaultLabel);
            const labelInput = label.querySelector('input');
            labelInput.maxLength = 80;
            labelInput.required = true;
            labelInput.addEventListener('input', () => action.label = labelInput.value);

            const targetType = element('select', {attributes: {'aria-label': 'Art des Linkziels'}, children: [
                element('option', {text: 'URL verlinken', attributes: {value: 'url'}}),
                element('option', {text: 'CMS-Seite verlinken', attributes: {value: 'page'}}),
            ]});
            targetType.value = action.pageId ? 'page' : 'url';
            const targetField = element('div', {className: 'event-call-action-target'});
            const renderTarget = () => {
                if (targetType.value === 'page') {
                    const pageSelect = element('select', {attributes: {'aria-label': 'Verlinkte CMS-Seite'}});
                    pageSelect.append(element('option', {text: 'Seite auswählen …', attributes: {value: ''}}));
                    flattenPageTree(buildPageTree(pages || [])).forEach(({page: candidate, depth}) => {
                        pageSelect.append(element('option', {
                            text: `${'— '.repeat(depth)}${candidate.title}${candidate.visible ? '' : ' (ausgeblendet)'}`,
                            attributes: {value: candidate.id},
                        }));
                    });
                    pageSelect.value = action.pageId || '';
                    pageSelect.required = true;
                    pageSelect.addEventListener('change', () => action.pageId = pageSelect.value || null);
                    targetField.replaceChildren(element('label', {className: 'field', children: [element('span', {text: 'Verlinkte Seite'}), pageSelect]}));
                    return;
                }
                const url = field('URL', `event-action-url-${dialogKey}-${actionIndex}`, action.url || '/');
                const urlInput = url.querySelector('input');
                urlInput.maxLength = 2048;
                urlInput.required = true;
                urlInput.addEventListener('input', () => action.url = urlInput.value);
                targetField.replaceChildren(url);
            };
            targetType.addEventListener('change', () => {
                if (targetType.value === 'page') {
                    action.url = null;
                } else {
                    action.pageId = null;
                    action.url = '/';
                }
                renderTarget();
            });
            renderTarget();
            const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', title: 'Aktionsbutton entfernen', 'aria-label': 'Aktionsbutton entfernen'}});
            remove.addEventListener('click', () => {
                callToActions.splice(actionIndex, 1);
                renderActions();
            });

            return element('div', {className: 'event-call-action-editor-row', children: [
                label,
                element('label', {className: 'field', children: [element('span', {text: 'Linkziel'}), targetType]}),
                targetField,
                remove,
            ]});
        }));
    };
    const addAction = element('button', {className: 'secondary-button', text: '＋ Aktionsbutton hinzufügen', attributes: {type: 'button'}});
    addAction.addEventListener('click', () => {
        callToActions.push({label: callToActions.length === 0 ? defaultLabel : 'Mehr erfahren', url: '/', pageId: null});
        renderActions();
    });
    renderActions();

    return element('fieldset', {className: 'event-call-action-editor', children: [
        element('legend', {text: legend}),
        element('small', {text: hint}),
        actionList,
        addAction,
    ]});
};

/**
 * Öffnet den Dialog für eine neue/zu bearbeitende Veranstaltung bzw. einen Arbeitseinsatz. Mit
 * `prefill` (einer Vorlage, siehe `openEventTemplateDialog`/„Vorlage verwenden") werden alle Felder
 * außer Datum/Uhrzeit aus der Vorlage übernommen — nur gültig, wenn `schedule` null ist (Vorlagen
 * werden nur beim Neuanlegen vorbelegt, nie beim Bearbeiten eines bestehenden Eintrags).
 */
const openEventDialog = (schedule, kind, onSaved, handlers, prefill = null) => {
    const draft = {
        content: schedule?.content || prefill?.content || '',
        mediaUrl: schedule?.mediaUrl || prefill?.mediaUrl || null,
        mediaAlt: schedule?.mediaAlt || prefill?.mediaAlt || null,
        mediaSource: schedule?.mediaSource || prefill?.mediaSource || null,
    };
    const effectiveKind = schedule?.kind || kind;
    const dialogKey = schedule?.id || 'new';
    const dialog = element('dialog', {className: 'event-schedule-dialog'});

    const title = field('Überschrift', 'event-title-' + dialogKey, schedule?.title || prefill?.title || '');
    const date = field('Datum', 'event-date-' + dialogKey, schedule?.date || '', 'date');
    const time = field('Uhrzeit', 'event-time-' + dialogKey, schedule?.time || '14:00', 'time');
    title.querySelector('input').required = true;
    date.querySelector('input').required = true;
    time.querySelector('input').required = true;

    const visible = element('input', {attributes: {type: 'checkbox'}});
    visible.checked = schedule ? schedule.visible !== false : true;

    const helpEnabled = element('input', {attributes: {type: 'checkbox'}});
    helpEnabled.checked = schedule ? schedule.helpEnabled === true : (prefill ? prefill.helpEnabled === true : effectiveKind === 'work_assignment');
    const helpLabel = field('Beschriftung des Buttons', 'event-help-label-' + dialogKey, schedule?.helpButtonLabel || prefill?.helpButtonLabel || 'Ich möchte helfen!');
    const helpLabelInput = helpLabel.querySelector('input');

    const message = formMessage();

    // Ohne Vorlage werden bei einer neuen Veranstaltung alle als „immer einbinden“ markierten
    // Aktivitäten automatisch vorbelegt; mit Vorlage übernimmt deren eigene Zuordnung (auch wenn
    // leer — die Vorlage ist dann bewusst maßgeblich), beim Bearbeiten bleiben die gespeicherten
    // Zuordnungen unangetastet.
    const activities = schedule
        ? schedule.activities.map((activity) => ({...activity}))
        : prefill
            ? prefill.activities.map((activity) => ({...activity}))
            : (handlers.activities || [])
                .filter((activity) => activity.active && activity.alwaysIncluded)
                .map((activity) => ({
                    activityId: activity.id,
                    requiredHelpers: String(activity.defaultRequiredHelpers ?? 1),
                    time: null, meetTime: null, meetPlace: null, remark: null,
                }));
    const {editor: activityEditor, list: activityList} = buildActivityAssignmentEditor(activities, handlers, dialogKey, message);
    const helpConfiguration = element('div', {className: 'event-help-configuration', children: [helpLabel, activityEditor]});
    helpConfiguration.hidden = !helpEnabled.checked;
    helpEnabled.addEventListener('change', () => helpConfiguration.hidden = !helpEnabled.checked);

    const callToActions = (schedule?.callToActions || prefill?.callToActions || []).map((action) => ({...action}));
    const callToActionEditor = buildCallToActionEditor(callToActions, handlers.pages, dialogKey);

    const submitLabel = schedule
        ? 'Änderungen speichern'
        : (effectiveKind === 'work_assignment' ? 'Arbeitseinsatz anlegen' : 'Veranstaltung anlegen');
    const submit = element('button', {
        className: 'button event-dialog-action event-dialog-action-save',
        attributes: {type: 'submit', title: submitLabel, 'aria-label': submitLabel},
        children: [
            element('span', {className: 'event-dialog-action-icon', text: '✓', attributes: {'aria-hidden': 'true'}}),
            element('span', {className: 'event-dialog-action-label', text: submitLabel}),
        ],
    });
    const cancel = element('button', {
        className: 'secondary-button event-dialog-action event-dialog-action-cancel',
        attributes: {type: 'button', title: 'Abbrechen', 'aria-label': 'Abbrechen'},
        children: [
            element('span', {className: 'event-dialog-action-icon', text: '×', attributes: {'aria-hidden': 'true'}}),
            element('span', {className: 'event-dialog-action-label', text: 'Abbrechen'}),
        ],
    });
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
    const actions = [cancel, submit];
    if (schedule) {
        const deleteButton = element('button', {
            className: 'button danger-button event-dialog-action event-dialog-action-delete',
            attributes: {type: 'button', title: 'Löschen', 'aria-label': 'Löschen'},
            children: [
                element('span', {className: 'event-dialog-action-icon', text: '⌫', attributes: {'aria-hidden': 'true'}}),
                element('span', {className: 'event-dialog-action-label', text: 'Löschen'}),
            ],
        });
        deleteButton.addEventListener('click', async () => {
            const confirmed = await confirmAction(
                `„${schedule.title}“ löschen?`,
                'Der Eintrag wird endgültig entfernt. Bereits eingegangene Helferanmeldungen bleiben im Modul „Veranstaltungshelfer“ einsehbar.',
                'Löschen',
            );
            if (!confirmed) return;
            try {
                await request('/api/admin/v1/events/' + schedule.id, {method: 'DELETE'});
                toast('Der Eintrag wurde gelöscht.');
                dialog.close();
                await onSaved();
            } catch (error) {
                toast(error.message, 'error');
            }
        });
        actions.unshift(deleteButton);
    }

    const form = element('form', {className: 'event-schedule-dialog-content', children: [
        element('header', {children: [
            element('div', {children: [
                element('p', {className: 'eyebrow', text: EVENT_SCHEDULE_KIND_LABELS[effectiveKind] || effectiveKind}),
                element('h2', {text: schedule ? schedule.title : (effectiveKind === 'work_assignment' ? 'Arbeitseinsatz anlegen' : 'Veranstaltung anlegen')}),
            ]}),
        ]}),
        title,
        element('div', {className: 'form-grid', children: [date, time]}),
        element('div', {className: 'field', children: [
            element('span', {text: 'Zusatzinformationen (optional)'}),
            richTextEditor(draft, 'event-' + dialogKey, null, 'Zusatzinformationen zur Veranstaltung'),
        ]}),
        collectionItemMediaEditor(draft, 'event-' + dialogKey),
        element('label', {className: 'check-field', children: [visible, element('span', {text: 'Im Frontend sichtbar'})]}),
        element('label', {className: 'check-field event-help-option', children: [
            helpEnabled,
            element('span', {text: 'Im Frontend den Button „Ich möchte helfen!“ mit Anmeldeformular anzeigen'}),
        ]}),
        helpConfiguration,
        callToActionEditor,
        message,
        element('div', {className: 'confirm-dialog-actions', children: actions}),
    ]});

    cancel.addEventListener('click', () => dialog.close());
    close.addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        try {
            const countInputs = activityList.querySelectorAll('.event-activity-field-count input');
            const invalidCount = Array.from(countInputs).find((input, index) => {
                const value = Number(input.value);
                const valid = input.value.trim() !== '' && Number.isInteger(value) && value > 0 && value <= 999;
                input.setCustomValidity(valid ? '' : 'Bitte eine ganze Zahl zwischen 1 und 999 eingeben.');
                input.toggleAttribute('aria-invalid', !valid);
                if (valid) activities[index].requiredHelpers = value;
                return !valid;
            });
            if (invalidCount) {
                message.textContent = 'Die Anzahl der benötigten Helfer muss zwischen 1 und 999 liegen.';
                invalidCount.reportValidity();
                invalidCount.focus();
                submit.disabled = false;
                return;
            }
            const payload = {
                title: title.querySelector('input').value,
                date: date.querySelector('input').value,
                time: time.querySelector('input').value,
                content: draft.content,
                mediaUrl: draft.mediaUrl,
                mediaAlt: draft.mediaAlt,
                mediaSource: draft.mediaSource,
                helpEnabled: helpEnabled.checked,
                helpButtonLabel: helpLabelInput.value || null,
                visible: visible.checked,
                activities: activities.map((activity) => ({
                    activityId: activity.activityId,
                    requiredHelpers: activity.requiredHelpers,
                    time: activity.time || null,
                    meetTime: activity.meetTime || null,
                    meetPlace: activity.meetPlace || null,
                    remark: activity.remark || null,
                })),
                callToActions,
            };
            if (schedule) {
                await request('/api/admin/v1/events/' + schedule.id, {method: 'PUT', body: JSON.stringify(payload)});
                toast('Änderungen wurden gespeichert.');
            } else {
                await request('/api/admin/v1/events', {method: 'POST', body: JSON.stringify({...payload, kind: effectiveKind})});
                toast(effectiveKind === 'work_assignment' ? 'Arbeitseinsatz wurde angelegt.' : 'Veranstaltung wurde angelegt.');
            }
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

let eventScheduleKindFilter = '';
const showEvents = async () => {
    const [scheduleData, activityData] = await Promise.all([
        request('/api/admin/v1/events'),
        request('/api/admin/v1/event-activities'),
    ]);
    let pages = [];
    try {
        pages = (await request('/api/admin/v1/pages')).items;
    } catch {
        pages = [];
    }
    const handlers = {activities: activityData.items, pages};

    const filterSelect = element('select', {attributes: {'aria-label': 'Nach Art filtern'}, children: [
        element('option', {text: 'Alle', attributes: {value: ''}}),
        element('option', {text: 'Nur Veranstaltungen', attributes: {value: 'event'}}),
        element('option', {text: 'Nur Arbeitseinsätze', attributes: {value: 'work_assignment'}}),
    ]});
    filterSelect.value = eventScheduleKindFilter;
    filterSelect.addEventListener('change', async () => {
        eventScheduleKindFilter = filterSelect.value;
        await showEventManagement();
    });

    const items = scheduleData.items.filter((item) => !eventScheduleKindFilter || item.kind === eventScheduleKindFilter);

    const renderRow = (item) => {
        const row = element('button', {
            className: `activity-list-row event-kind-${item.kind}${item.visible ? '' : ' is-inactive'}`,
            attributes: {type: 'button', ...(canEditModule('events') ? {} : {disabled: 'disabled'})},
            children: [
                element('span', {className: 'activity-list-copy', children: [
                    element('strong', {text: item.title}),
                    element('small', {text: `${new Date(`${item.date}T00:00:00`).toLocaleDateString('de-DE')} · ${item.time} Uhr`}),
                ]}),
                element('span', {className: `status-badge event-kind-badge-${item.kind}`, text: EVENT_SCHEDULE_KIND_LABELS[item.kind] || item.kind}),
                ...(item.visible ? [] : [element('span', {className: 'status-badge status-inactive', text: 'Ausgeblendet'})]),
                ...(canEditModule('events') ? [element('span', {className: 'activity-list-edit', text: 'Bearbeiten ›'})] : []),
            ],
        });
        if (canEditModule('events')) row.addEventListener('click', () => openEventDialog(item, item.kind, showEventManagement, handlers));
        return row;
    };

    const {currentYear, todayItems, upcomingItems, completedCurrentYearItems, archiveByYear} =
        bucketItemsByDate(items, (item) => item.date, (item) => item.time);
    const section = (title, sectionItems, modifier = '') => element('section', {
        className: `event-helper-section ${modifier}`.trim(),
        children: [
            element('div', {className: 'event-helper-section-heading', children: [
                element('h3', {text: title}),
                element('span', {className: 'status-badge', text: String(sectionItems.length)}),
            ]}),
            element('div', {className: 'activity-list', children: sectionItems.map(renderRow)}),
        ],
    });
    const archive = (title, sectionItems) => element('details', {className: 'event-helper-archive', children: [
        element('summary', {children: [
            element('strong', {text: title}),
            element('span', {className: 'status-badge', text: String(sectionItems.length)}),
        ]}),
        element('div', {className: 'event-helper-archive-list', children: [element('div', {className: 'activity-list', children: sectionItems.map(renderRow)})]}),
    ]});
    const sections = [
        ...(todayItems.length ? [section('Heute', todayItems, 'event-helper-section-today')] : []),
        ...(upcomingItems.length ? [section('Kommende', upcomingItems)] : []),
        ...(completedCurrentYearItems.length ? [archive(`Abgeschlossen ${currentYear}`, completedCurrentYearItems)] : []),
        ...[...archiveByYear.entries()].sort(([firstYear], [secondYear]) => secondYear - firstYear)
            .map(([year, yearItems]) => archive(`Archiv ${year}`, yearItems)),
    ];

    const heading = sectionHeading('Veranstaltungen', 'Veranstaltungen und Arbeitseinsätze verwalten');
    if (canEditModule('events')) {
        const createEvent = element('button', {className: 'button event-create-button event-create-event', text: '＋ Veranstaltung erstellen', attributes: {type: 'button'}});
        const createWorkAssignment = element('button', {className: 'button event-create-button event-create-work-assignment', text: '＋ Arbeitseinsatz erstellen', attributes: {type: 'button'}});
        createEvent.addEventListener('click', () => openEventDialog(null, 'event', showEventManagement, handlers));
        createWorkAssignment.addEventListener('click', () => openEventDialog(null, 'work_assignment', showEventManagement, handlers));
        heading.append(createEvent, createWorkAssignment);
    }

    workspace.replaceChildren(
        heading,
        element('div', {className: 'management-toolbar', children: [filterSelect, element('span', {text: `${items.length} Einträge`})]}),
        element('div', {className: 'event-helper-groups', children: sections.length ? sections : [emptyState('Noch keine Veranstaltungen oder Arbeitseinsätze angelegt.')]}),
    );
};


/**
 * Öffnet den Dialog für eine neue/zu bearbeitende Vorlage — wie `openEventDialog`, aber ohne
 * Datum/Uhrzeit/„Im Frontend sichtbar" (eine Vorlage hat keinen eigenen Termin) und gegen
 * `/api/admin/v1/event-templates` statt `/api/admin/v1/events`.
 */
const openEventTemplateDialog = (template, kind, onSaved, handlers) => {
    const draft = {
        content: template?.content || '',
        mediaUrl: template?.mediaUrl || null,
        mediaAlt: template?.mediaAlt || null,
        mediaSource: template?.mediaSource || null,
    };
    const effectiveKind = template?.kind || kind;
    const dialogKey = template?.id || 'new';
    const dialog = element('dialog', {className: 'event-schedule-dialog'});

    const title = field('Überschrift', 'event-template-title-' + dialogKey, template?.title || '');
    title.querySelector('input').required = true;

    const helpEnabled = element('input', {attributes: {type: 'checkbox'}});
    helpEnabled.checked = template ? template.helpEnabled === true : effectiveKind === 'work_assignment';
    const helpLabel = field('Beschriftung des Buttons', 'event-template-help-label-' + dialogKey, template?.helpButtonLabel || 'Ich möchte helfen!');
    const helpLabelInput = helpLabel.querySelector('input');

    const message = formMessage();

    const activities = template ? template.activities.map((activity) => ({...activity})) : [];
    const {editor: activityEditor, list: activityList} = buildActivityAssignmentEditor(activities, handlers, dialogKey, message);
    const helpConfiguration = element('div', {className: 'event-help-configuration', children: [helpLabel, activityEditor]});
    helpConfiguration.hidden = !helpEnabled.checked;
    helpEnabled.addEventListener('change', () => helpConfiguration.hidden = !helpEnabled.checked);

    const callToActions = (template?.callToActions || []).map((action) => ({...action}));
    const callToActionEditor = buildCallToActionEditor(callToActions, handlers.pages, dialogKey);

    const submitLabel = template ? 'Änderungen speichern' : 'Vorlage anlegen';
    const submit = element('button', {
        className: 'button event-dialog-action event-dialog-action-save',
        attributes: {type: 'submit', title: submitLabel, 'aria-label': submitLabel},
        children: [
            element('span', {className: 'event-dialog-action-icon', text: '✓', attributes: {'aria-hidden': 'true'}}),
            element('span', {className: 'event-dialog-action-label', text: submitLabel}),
        ],
    });
    const cancel = element('button', {
        className: 'secondary-button event-dialog-action event-dialog-action-cancel',
        attributes: {type: 'button', title: 'Abbrechen', 'aria-label': 'Abbrechen'},
        children: [
            element('span', {className: 'event-dialog-action-icon', text: '×', attributes: {'aria-hidden': 'true'}}),
            element('span', {className: 'event-dialog-action-label', text: 'Abbrechen'}),
        ],
    });
    const close = element('button', {className: 'event-help-close', text: '×', attributes: {type: 'button', 'aria-label': 'Dialog schließen'}});
    const actions = [cancel, submit];
    if (template) {
        const deleteButton = element('button', {
            className: 'button danger-button event-dialog-action event-dialog-action-delete',
            attributes: {type: 'button', title: 'Löschen', 'aria-label': 'Löschen'},
            children: [
                element('span', {className: 'event-dialog-action-icon', text: '⌫', attributes: {'aria-hidden': 'true'}}),
                element('span', {className: 'event-dialog-action-label', text: 'Löschen'}),
            ],
        });
        deleteButton.addEventListener('click', async () => {
            const confirmed = await confirmAction(
                `„${template.title}“ löschen?`,
                'Die Vorlage wird endgültig entfernt. Bereits erstellte Veranstaltungen/Arbeitseinsätze bleiben unverändert.',
                'Löschen',
            );
            if (!confirmed) return;
            try {
                await request('/api/admin/v1/event-templates/' + template.id, {method: 'DELETE'});
                toast('Die Vorlage wurde gelöscht.');
                dialog.close();
                await onSaved();
            } catch (error) {
                toast(error.message, 'error');
            }
        });
        actions.unshift(deleteButton);
    }

    const form = element('form', {className: 'event-schedule-dialog-content', children: [
        element('header', {children: [
            element('div', {children: [
                element('p', {className: 'eyebrow', text: `Vorlage · ${EVENT_SCHEDULE_KIND_LABELS[effectiveKind] || effectiveKind}`}),
                element('h2', {text: template ? template.title : 'Vorlage anlegen'}),
            ]}),
        ]}),
        title,
        element('div', {className: 'field', children: [
            element('span', {text: 'Zusatzinformationen (optional)'}),
            richTextEditor(draft, 'event-template-' + dialogKey, null, 'Zusatzinformationen zur Veranstaltung'),
        ]}),
        collectionItemMediaEditor(draft, 'event-template-' + dialogKey),
        element('label', {className: 'check-field event-help-option', children: [
            helpEnabled,
            element('span', {text: 'Im Frontend den Button „Ich möchte helfen!“ mit Anmeldeformular anzeigen'}),
        ]}),
        helpConfiguration,
        callToActionEditor,
        message,
        element('div', {className: 'confirm-dialog-actions', children: actions}),
    ]});

    cancel.addEventListener('click', () => dialog.close());
    close.addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        try {
            const countInputs = activityList.querySelectorAll('.event-activity-field-count input');
            const invalidCount = Array.from(countInputs).find((input, index) => {
                const value = Number(input.value);
                const valid = input.value.trim() !== '' && Number.isInteger(value) && value > 0 && value <= 999;
                input.setCustomValidity(valid ? '' : 'Bitte eine ganze Zahl zwischen 1 und 999 eingeben.');
                input.toggleAttribute('aria-invalid', !valid);
                if (valid) activities[index].requiredHelpers = value;
                return !valid;
            });
            if (invalidCount) {
                message.textContent = 'Die Anzahl der benötigten Helfer muss zwischen 1 und 999 liegen.';
                invalidCount.reportValidity();
                invalidCount.focus();
                submit.disabled = false;
                return;
            }
            const payload = {
                title: title.querySelector('input').value,
                content: draft.content,
                mediaUrl: draft.mediaUrl,
                mediaAlt: draft.mediaAlt,
                mediaSource: draft.mediaSource,
                helpEnabled: helpEnabled.checked,
                helpButtonLabel: helpLabelInput.value || null,
                activities: activities.map((activity) => ({
                    activityId: activity.activityId,
                    requiredHelpers: activity.requiredHelpers,
                    time: activity.time || null,
                    meetTime: activity.meetTime || null,
                    meetPlace: activity.meetPlace || null,
                    remark: activity.remark || null,
                })),
                callToActions,
            };
            if (template) {
                await request('/api/admin/v1/event-templates/' + template.id, {method: 'PUT', body: JSON.stringify(payload)});
                toast('Änderungen wurden gespeichert.');
            } else {
                await request('/api/admin/v1/event-templates', {method: 'POST', body: JSON.stringify({...payload, kind: effectiveKind})});
                toast('Vorlage wurde angelegt.');
            }
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
 * Reiter „Vorlagen": wiederverwendbare Vorlagen für Veranstaltungen (grün) und Arbeitseinsätze
 * (gelb), dieselben `event-kind-*`/`event-kind-badge-*`-Klassen wie in „Termine". „Vorlage
 * verwenden" öffnet den regulären Anlage-Dialog vorausgefüllt (siehe `openEventDialog`s
 * `prefill`-Parameter) — nur Datum/Uhrzeit sind danach noch auszufüllen.
 */
const showEventTemplates = async () => {
    const [templateData, activityData, pageData] = await Promise.all([
        request('/api/admin/v1/event-templates'),
        request('/api/admin/v1/event-activities'),
        request('/api/admin/v1/pages'),
    ]);
    const handlers = {activities: activityData.items, pages: pageData.items};

    const heading = sectionHeading('Vorlagen', 'Wiederverwendbare Vorlagen für Veranstaltungen und Arbeitseinsätze anlegen und verwenden');
    if (canEditModule('events')) {
        const createEvent = element('button', {className: 'button event-create-button event-create-event', text: '＋ Vorlage (Veranstaltung)', attributes: {type: 'button'}});
        const createWorkAssignment = element('button', {className: 'button event-create-button event-create-work-assignment', text: '＋ Vorlage (Arbeitseinsatz)', attributes: {type: 'button'}});
        createEvent.addEventListener('click', () => openEventTemplateDialog(null, 'event', showEventManagement, handlers));
        createWorkAssignment.addEventListener('click', () => openEventTemplateDialog(null, 'work_assignment', showEventManagement, handlers));
        heading.append(createEvent, createWorkAssignment);
    }

    // Keine verschachtelten <button>: die Zeile selbst ist kein Button (anders als bei „Termine"/
    // „Aktivitäten"), da „Vorlage verwenden" als eigenständiger, nicht verschachtelter Button
    // daneben steht.
    const renderRow = (template) => {
        const titleButton = element('button', {
            className: 'activity-list-row-title',
            attributes: {type: 'button', title: `${template.title} bearbeiten`, ...(canEditModule('events') ? {} : {disabled: 'disabled'})},
            children: [
                element('span', {className: 'activity-list-copy', children: [
                    element('strong', {text: template.title}),
                    element('small', {text: `${template.activities.length} ${template.activities.length === 1 ? 'Aktivität' : 'Aktivitäten'} zugeordnet`}),
                ]}),
            ],
        });
        if (canEditModule('events')) titleButton.addEventListener('click', () => openEventTemplateDialog(template, template.kind, showEventManagement, handlers));

        const useTemplate = element('button', {className: 'secondary-button', text: 'Vorlage verwenden', attributes: {type: 'button'}});
        useTemplate.addEventListener('click', () => openEventDialog(null, template.kind, showEventManagement, handlers, template));

        return element('div', {className: `activity-list-row event-kind-${template.kind}`, children: [
            titleButton,
            element('span', {className: `status-badge event-kind-badge-${template.kind}`, text: EVENT_SCHEDULE_KIND_LABELS[template.kind] || template.kind}),
            useTemplate,
        ]});
    };

    workspace.replaceChildren(
        heading,
        element('div', {className: 'activity-list', children: templateData.items.length
            ? templateData.items.map(renderRow)
            : [emptyState('Noch keine Vorlagen angelegt.')]}),
    );
};

const eventTabsBySlug = {termine: 'events', helfer: 'helpers', aktivitaeten: 'activities', vorlagen: 'templates'};
const eventSlugsByTab = Object.fromEntries(Object.entries(eventTabsBySlug).map(([slug, tab]) => [tab, slug]));
let activeEventTab = null;

const showEventManagement = async (segments = []) => {
    if (segments[0] === 'veranstaltungen' && eventTabsBySlug[segments[1]]) {
        activeEventTab = eventTabsBySlug[segments[1]];
    }
    const tabs = [
        ...(hasModule('events') ? [['events', 'Veranstaltungen', showEvents]] : []),
        ...(hasModule('event_helpers') ? [['helpers', 'Veranstaltungshelfer', showEventHelpers]] : []),
        ...(hasModule('activities') ? [['activities', 'Aktivitäten', showActivities]] : []),
        ...(hasModule('events') ? [['templates', 'Vorlagen', showEventTemplates]] : []),
    ];
    if (!tabs.some(([key]) => key === activeEventTab)) activeEventTab = tabs[0]?.[0] || null;
    if (activeEventTab) {
        setAdminPath(['veranstaltungen', eventSlugsByTab[activeEventTab]], true);
    }

    const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'Veranstaltung'}, children: tabs.map(([key, label]) => {
        const button = element('a', {
            className: `sub-tab${key === activeEventTab ? ' active' : ''}`,
            text: label,
            attributes: {href: adminPath('veranstaltungen', eventSlugsByTab[key])},
        });
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            activeEventTab = key;
            setAdminPath(['veranstaltungen', eventSlugsByTab[key]]);
            await showEventManagement();
        });

        return button;
    })});
    const active = tabs.find(([key]) => key === activeEventTab);
    if (!active) {
        workspace.replaceChildren(tabStrip, emptyState('Für diesen Zugang ist kein Bereich der Veranstaltungsverwaltung freigeschaltet.'));
        return;
    }
    // showEvents/showEventHelpers/showActivities schreiben wie jedes andere Modul direkt in
    // `workspace`. Deren Ergebnis wird danach in ein Tab-Panel umgehängt, damit Tab-Leiste und
    // Inhalt gemeinsam sichtbar bleiben.
    await active[2]();
    const panel = element('div', {className: 'sub-tab-panel', children: [...workspace.children]});
    workspace.replaceChildren(tabStrip, panel);
};

export {showEventManagement, buildCallToActionEditor};
