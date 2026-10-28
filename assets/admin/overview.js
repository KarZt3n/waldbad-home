// Startseite der Redaktion: offene Aufgaben aller freigeschalteten Module als Kennzahlen mit
// direktem Sprung in den zuständigen Bereich.

import {element, request} from '../core.js';
import {emptyState, sectionHeading} from './ui.js';
import {adminPath, workspace} from './shell.js';

// Grundreihenfolge der Gruppen; eine Gruppe kann mehrere CMS-Module bündeln. Gruppen mit offenen
// Aufgaben rücken nach oben, offene Aufgaben der Mitgliederverwaltung stehen immer ganz oben.
const GROUPS = [
    {key: 'pages', label: 'Seiten'},
    {key: 'events', label: 'Veranstaltung'},
    {key: 'members', label: 'Mitgliederverwaltung', pinnedWhenOpen: true},
    {key: 'contact', label: 'Kontakt und Feedback'},
    {key: 'rental', label: 'Vermietung · Sauna'},
];
const groupRank = (group, openCount) => {
    if (!openCount) return 2;

    return group.pinnedWhenOpen ? 0 : 1;
};

// `due`: Der Zeitpunkt ist ein anstehender Termin (nächster fälliger), sonst der älteste Eingang.
const TASKS = {
    membership_applications: {group: 'members', label: 'Mitgliedsanträge', hint: 'warten auf Entscheidung', path: ['mitglieder', 'antraege']},
    member_messages: {group: 'members', label: 'Nachrichten von Mitgliedern', hint: 'noch nicht erledigt', path: ['mitglieder', 'nachrichten']},
    contact_requests: {group: 'contact', label: 'Kontaktanfragen', hint: 'offen', path: ['kontakt-feedback', 'kontaktanfragen']},
    guestbook_entries: {group: 'contact', label: 'Gästebucheinträge', hint: 'zu moderieren', path: ['kontakt-feedback', 'gaestebuch']},
    sauna_bookings: {group: 'rental', label: 'Anmeldungen', hint: 'zu bestätigen', path: ['vermietung', 'sauna', 'anmeldungen']},
    sauna_duties: {group: 'rental', label: 'Offene Dienste', hint: 'ohne Dienst in den nächsten 4 Wochen', path: ['vermietung', 'sauna', 'dienstplan'], due: true},
    event_help_requests: {group: 'events', label: 'Helferanmeldungen', hint: 'neu eingegangen', path: ['veranstaltungen', 'helfer']},
    pages_in_review: {group: 'pages', label: 'Zur Prüfung', hint: 'warten auf Veröffentlichung', path: ['seiten']},
    pages_with_unpublished_changes: {group: 'pages', label: 'Unveröffentlichte Änderungen', hint: 'an bereits veröffentlichten Seiten', path: ['seiten']},
};

const DAY_MS = 24 * 60 * 60 * 1000;
const ageLabel = (isoDate) => {
    const days = Math.floor((Date.now() - new Date(isoDate).getTime()) / DAY_MS);
    if (days < 1) return 'älteste von heute';
    if (days === 1) return 'älteste seit gestern';

    return `älteste seit ${days} Tagen`;
};
const dueLabel = (isoDate) => `nächster am ${new Date(isoDate).toLocaleString('de-DE', {weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'})} Uhr`;

// Sprung über das History-Routing der Shell (`window.onpopstate` → `openAdminRoute`), damit auch
// die Seitenleiste den aktiven Bereich markiert.
const navigate = (event, href) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    window.history.pushState({}, '', href);
    window.onpopstate?.(new PopStateEvent('popstate'));
};

const taskTile = (task) => {
    const definition = TASKS[task.type];
    const href = adminPath(...definition.path);
    const open = task.count > 0;
    const tile = element('a', {
        className: `overview-tile${open ? ' is-open' : ''}`,
        attributes: {href, 'aria-label': `${definition.label}: ${task.count} ${definition.hint}`},
        children: [
            element('span', {className: 'overview-tile-count', text: String(task.count)}),
            element('span', {className: 'overview-tile-label', text: definition.label}),
            element('small', {text: open ? `${definition.hint} · ${(definition.due ? dueLabel : ageLabel)(task.earliestAt)}` : 'nichts zu tun'}),
        ],
    });
    tile.addEventListener('click', (event) => navigate(event, href));

    return tile;
};

const showOverview = async () => {
    const data = await request('/api/admin/v1/overview');
    const tasks = data.items.filter((task) => TASKS[task.type]);
    const openTasks = tasks.filter((task) => task.count > 0);
    const total = openTasks.reduce((sum, task) => sum + task.count, 0);
    const summary = total === 0
        ? 'Alles erledigt – es wartet gerade nichts auf dich.'
        : `${total} ${total === 1 ? 'offener Eintrag wartet' : 'offene Einträge warten'} auf Bearbeitung.`;

    const groups = GROUPS.map((group) => {
        const groupTasks = tasks.filter((task) => TASKS[task.type].group === group.key);
        if (!groupTasks.length) return null;
        const openCount = groupTasks.reduce((sum, task) => sum + task.count, 0);

        return {rank: groupRank(group, openCount), node: element('section', {className: `overview-group${openCount ? ' is-open' : ''}`, attributes: {'aria-label': group.label}, children: [
            element('header', {className: 'overview-group-header', children: [
                element('h3', {text: group.label}),
                element('span', {className: `status-badge${openCount ? ' status-pending' : ''}`, text: openCount ? `${openCount} offen` : 'erledigt'}),
            ]}),
            element('div', {className: 'overview-grid', children: [
                ...groupTasks.filter((task) => task.count > 0).map(taskTile),
                ...groupTasks.filter((task) => task.count === 0).map(taskTile),
            ]}),
        ]})};
    }).filter(Boolean)
        // `sort` ist stabil: Innerhalb eines Rangs bleibt die Grundreihenfolge erhalten.
        .sort((left, right) => left.rank - right.rank)
        .map((group) => group.node);

    workspace.replaceChildren(
        sectionHeading('Übersicht', summary),
        groups.length
            ? element('div', {className: 'overview-groups', children: groups})
            : emptyState('Für deine freigeschalteten Module gibt es keine Aufgaben in der Übersicht.'),
    );
};

export {showOverview};
