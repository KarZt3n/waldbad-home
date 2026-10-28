// Modul „Kontakt und Feedback": Kontaktanfragen und Gästebuch-Moderation als Unter-Reiter unter
// einer gemeinsamen Route (`/admin/kontakt-feedback/...`).

import {confirmAction, element, request, toast} from '../core.js';
import {actionButton, emptyState, focusLinkedItem, sectionHeading, whileNavigating} from './ui.js';
import {canEditModule, hasModule} from './session.js';
import {adminPath, setAdminPath, workspace} from './shell.js';

const showGuestbook = async () => {
    const data = await request('/api/admin/v1/guestbook-entries');
    const pendingEntries = data.items.filter((entry) => entry.status === 'pending');
    const publishedEntries = data.items.filter((entry) => entry.status === 'published');
    const otherEntries = data.items.filter((entry) => !['pending', 'published'].includes(entry.status));
    const archive = (title, entries) => element('details', {className: 'guestbook-archive', children: [
        element('summary', {children: [
            element('strong', {text: title}),
            element('span', {className: 'status-badge', text: String(entries.length)}),
        ]}),
        element('div', {
            className: 'card-list guestbook-archive-list',
            children: entries.map((entry) => moderationCard(entry, refreshContactFeedback)),
        }),
    ]});

    workspace.replaceChildren(
        sectionHeading('Gästebuch', 'Neue Einträge prüfen und moderieren'),
        element('section', {className: 'guestbook-admin', children: [
            element('div', {className: 'guestbook-pending-heading', children: [
                element('h3', {text: 'Neue Einträge'}),
                element('span', {className: 'status-badge status-pending', text: String(pendingEntries.length)}),
            ]}),
            element('div', {
                className: 'card-list',
                children: pendingEntries.length
                    ? pendingEntries.map((entry) => moderationCard(entry, refreshContactFeedback))
                    : [emptyState('Keine neuen Gästebucheinträge vorhanden.')],
            }),
            ...(publishedEntries.length ? [archive('Veröffentlichte Einträge', publishedEntries)] : []),
            ...(otherEntries.length ? [archive('Abgelehnte Einträge und Spam', otherEntries)] : []),
        ]}),
    );
};

// Offene Anfragen oben, erledigte nach dem Jahr des Eingangs gruppiert (neuestes Jahr zuerst).
const showContact = async () => {
    const data = await request('/api/admin/v1/contact-requests');
    const openRequests = data.items.filter((item) => item.status !== 'resolved');
    const resolvedByYear = new Map();
    data.items.filter((item) => item.status === 'resolved').forEach((item) => {
        const year = new Date(item.submittedAt).getFullYear();
        resolvedByYear.set(year, [...(resolvedByYear.get(year) || []), item]);
    });
    const archives = [...resolvedByYear.entries()].sort(([left], [right]) => right - left).map(([year, items]) => element('details', {
        className: 'guestbook-archive',
        children: [
            element('summary', {children: [
                element('strong', {text: `Abgeschlossen ${year}`}),
                element('span', {className: 'status-badge', text: String(items.length)}),
            ]}),
            element('div', {className: 'card-list guestbook-archive-list', children: items.map((item) => contactCard(item, refreshContactFeedback))}),
        ],
    }));

    workspace.replaceChildren(
        sectionHeading('Kontaktanfragen', 'Anfragen beantworten, als erledigt markieren oder löschen'),
        element('section', {className: 'guestbook-admin', children: [
            element('div', {className: 'guestbook-pending-heading', children: [
                element('h3', {text: 'Offene Anfragen'}),
                element('span', {className: 'status-badge status-pending', text: String(openRequests.length)}),
            ]}),
            element('div', {
                className: 'card-list',
                children: openRequests.length
                    ? openRequests.map((item) => contactCard(item, refreshContactFeedback))
                    : [emptyState('Keine offenen Kontaktanfragen.')],
            }),
            ...archives,
        ]}),
    );
};

const guestbookStatusLabel = (status) => ({
    pending: 'Neu',
    published: 'Veröffentlicht',
    rejected: 'Abgelehnt',
    spam: 'Spam',
}[status] || status);
const moderationCard = (entry, refresh) => element('article', {className: `management-card guestbook-card status-${entry.status}`, attributes: {'data-link-target': entry.id}, children: [
    element('header', {children: [element('strong', {text: entry.displayName}), element('small', {text: guestbookStatusLabel(entry.status) + ' · ' + new Date(entry.submittedAt).toLocaleString('de-DE')})]}),
    element('p', {text: entry.message}),
    ...(entry.email ? [element('a', {text: entry.email, attributes: {href: 'mailto:' + entry.email}})] : []),
    ...(canEditModule('guestbook') ? [element('div', {className: 'card-actions', children: [
        ...(entry.status !== 'published' ? [actionButton('Freigeben', `/api/admin/v1/guestbook-entries/${entry.id}/approve`, refresh, 'button', {success: 'Gästebucheintrag wurde freigegeben.'})] : []),
        ...(entry.status !== 'rejected' ? [actionButton('Ablehnen', `/api/admin/v1/guestbook-entries/${entry.id}/reject`, refresh, 'secondary-button', {
            success: 'Gästebucheintrag wurde abgelehnt.',
            confirm: {title: 'Eintrag ablehnen?', description: 'Der Eintrag wird nicht im öffentlichen Gästebuch angezeigt.', label: 'Ablehnen'},
        })] : []),
        ...(entry.status !== 'spam' ? [actionButton('Spam', `/api/admin/v1/guestbook-entries/${entry.id}/mark-spam`, refresh, 'text-button danger', {
            success: 'Gästebucheintrag wurde als Spam markiert.',
            confirm: {title: 'Als Spam markieren?', description: 'Der Eintrag wird als Spam eingestuft und nicht veröffentlicht.', label: 'Als Spam markieren'},
        })] : []),
    ]})] : []),
]});
const deleteContactButton = (item, refresh) => {
    const button = element('button', {className: 'text-button danger', text: 'Löschen', attributes: {type: 'button'}});
    button.addEventListener('click', async () => {
        const confirmed = await confirmAction(
            'Kontaktanfrage löschen?',
            `Die Anfrage von ${item.name} wird dauerhaft gelöscht.`,
            'Löschen',
        );
        if (!confirmed) return;
        button.disabled = true;
        try {
            await request(`/api/admin/v1/contact-requests/${item.id}`, {method: 'DELETE'});
            toast('Kontaktanfrage wurde gelöscht.');
            await refresh();
        } catch (error) {
            toast(error.message, 'error');
            button.disabled = false;
        }
    });

    return button;
};
const contactCard = (item, refresh) => element('article', {className: 'management-card', attributes: {'data-link-target': item.id}, children: [
    element('header', {children: [
        element('strong', {text: item.subject || 'Kontaktanfrage'}),
        element('small', {text: `${item.status === 'resolved' ? 'Erledigt' : 'Offen'} · eingegangen ${new Date(item.submittedAt).toLocaleString('de-DE')}`}),
    ]}),
    element('p', {text: item.message}),
    element('a', {text: item.name + ' · ' + item.email, attributes: {href: 'mailto:' + item.email}}),
    ...(canEditModule('contact_requests') ? [element('div', {className: 'card-actions', children: [
        item.status === 'resolved'
            ? actionButton('Wieder öffnen', `/api/admin/v1/contact-requests/${item.id}/status/new`, refresh, 'secondary-button', {success: 'Kontaktanfrage ist wieder offen.'})
            : actionButton('Erledigt', `/api/admin/v1/contact-requests/${item.id}/status/resolved`, refresh, 'button', {success: 'Kontaktanfrage wurde als erledigt markiert.'}),
        deleteContactButton(item, refresh),
    ]})] : []),
]});

// Nach Aktionen auf einer Karte den ganzen Bereich samt Reiterleiste neu aufbauen; die Listen allein
// ersetzen den Arbeitsbereich und würden die Reiter entfernen.
const refreshContactFeedback = () => showContactFeedbackManagement();
const contactTabsBySlug = {kontaktanfragen: 'contact', gaestebuch: 'guestbook'};
const contactSlugsByTab = Object.fromEntries(Object.entries(contactTabsBySlug).map(([slug, tab]) => [tab, slug]));
let activeContactTab = null;

const showContactFeedbackManagement = async (segments = []) => {
    if (segments[0] === 'kontakt-feedback' && contactTabsBySlug[segments[1]]) {
        activeContactTab = contactTabsBySlug[segments[1]];
    }
    // `/admin/kontakt-feedback/{Reiter}/{id}` aus der Benachrichtigung über eine neue Anfrage bzw. einen neuen Eintrag.
    const focusId = segments[0] === 'kontakt-feedback' && contactTabsBySlug[segments[1]] === activeContactTab ? segments[2] || null : null;
    const tabs = [
        ...(hasModule('contact_requests') ? [['contact', 'Kontaktanfrage', showContact]] : []),
        ...(hasModule('guestbook') ? [['guestbook', 'Gästebuch', showGuestbook]] : []),
    ];
    if (!tabs.some(([key]) => key === activeContactTab)) activeContactTab = tabs[0]?.[0] || null;
    if (activeContactTab) {
        setAdminPath(['kontakt-feedback', contactSlugsByTab[activeContactTab]], true);
    }

    const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'Kontakt und Feedback'}, children: tabs.map(([key, label]) => {
        const button = element('a', {
            className: `sub-tab${key === activeContactTab ? ' active' : ''}`,
            text: label,
            attributes: {href: adminPath('kontakt-feedback', contactSlugsByTab[key])},
        });
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            activeContactTab = key;
            setAdminPath(['kontakt-feedback', contactSlugsByTab[key]]);
            await whileNavigating(() => showContactFeedbackManagement());
        });

        return button;
    })});
    const active = tabs.find(([key]) => key === activeContactTab);
    if (!active) {
        workspace.replaceChildren(tabStrip, emptyState('Für diesen Zugang ist kein Bereich von Kontakt und Feedback freigeschaltet.'));
        return;
    }
    await active[2]();
    const panel = element('div', {className: 'sub-tab-panel', children: [...workspace.children]});
    workspace.replaceChildren(tabStrip, panel);
    if (focusId) {
        focusLinkedItem(workspace, focusId, activeContactTab === 'guestbook'
            ? 'Der Gästebucheintrag aus dem Link wurde nicht gefunden.'
            : 'Die Kontaktanfrage aus dem Link wurde nicht gefunden.');
    }
};

export {showContactFeedbackManagement};
