// Modul „Kontakt und Feedback": Kontaktanfragen und Gästebuch-Moderation als Unter-Reiter unter
// einer gemeinsamen Route (`/admin/kontakt-feedback/...`).

import {element, request} from '../core.js';
import {actionButton, emptyState, sectionHeading} from './ui.js';
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
            children: entries.map((entry) => moderationCard(entry, showGuestbook)),
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
                    ? pendingEntries.map((entry) => moderationCard(entry, showGuestbook))
                    : [emptyState('Keine neuen Gästebucheinträge vorhanden.')],
            }),
            ...(publishedEntries.length ? [archive('Veröffentlichte Einträge', publishedEntries)] : []),
            ...(otherEntries.length ? [archive('Abgelehnte Einträge und Spam', otherEntries)] : []),
        ]}),
    );
};

const showContact = async () => {
    const data = await request('/api/admin/v1/contact-requests');
    workspace.replaceChildren(sectionHeading('Kontaktanfragen', 'Anfragen bearbeiten und abschließen'), element('div', {
        className: 'card-list', children: data.items.length ? data.items.map((item) => contactCard(item, showContact)) : [emptyState('Keine Kontaktanfragen vorhanden.')],
    }));
};

const guestbookStatusLabel = (status) => ({
    pending: 'Neu',
    published: 'Veröffentlicht',
    rejected: 'Abgelehnt',
    spam: 'Spam',
}[status] || status);
const moderationCard = (entry, refresh) => element('article', {className: `management-card guestbook-card status-${entry.status}`, children: [
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
const contactCard = (item, refresh) => element('article', {className: 'management-card', children: [
    element('header', {children: [element('strong', {text: item.subject || 'Kontaktanfrage'}), element('small', {text: item.status + ' · ' + new Date(item.submittedAt).toLocaleString('de-DE')})]}),
    element('p', {text: item.message}),
    element('a', {text: item.name + ' · ' + item.email, attributes: {href: 'mailto:' + item.email}}),
    ...(canEditModule('contact_requests') ? [element('div', {className: 'card-actions', children: [
        actionButton('In Bearbeitung', `/api/admin/v1/contact-requests/${item.id}/status/in_progress`, refresh, 'secondary-button', {success: 'Kontaktanfrage ist jetzt in Bearbeitung.'}),
        actionButton('Erledigt', `/api/admin/v1/contact-requests/${item.id}/status/resolved`, refresh, 'button', {success: 'Kontaktanfrage wurde als erledigt markiert.'}),
    ]})] : []),
]});

const contactTabsBySlug = {kontaktanfragen: 'contact', gaestebuch: 'guestbook'};
const contactSlugsByTab = Object.fromEntries(Object.entries(contactTabsBySlug).map(([slug, tab]) => [tab, slug]));
let activeContactTab = null;

const showContactFeedbackManagement = async (segments = []) => {
    if (segments[0] === 'kontakt-feedback' && contactTabsBySlug[segments[1]]) {
        activeContactTab = contactTabsBySlug[segments[1]];
    }
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
            await showContactFeedbackManagement();
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
};

export {showContactFeedbackManagement};
