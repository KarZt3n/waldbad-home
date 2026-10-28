// Rahmen der Redaktion: Seitenleiste/Menü, Routing (URL ↔ aktiver Bereich), Kopfzeile/Logout sowie
// die Sitzungs-Timer (Inaktivitäts-Logout, proaktiver Refresh). Kennt die einzelnen Bereiche
// (`admin/pages.js`, `admin/members.js`, …) nur über deren Einstiegsfunktion — nicht deren interne
// Unter-Reiter/Zustand (siehe jeweiliges Modul).
//
// `workspace` ist der DOM-Container, in den jeder Bereich seinen Inhalt schreibt: er wird erst mit
// `mountAdminShell()` (nach erfolgreichem Login) angelegt, andere Module importieren ihn als Live-
// Binding — zum Zeitpunkt, an dem ein Bereich `workspace` tatsächlich verwendet, ist die Shell
// immer schon fertig aufgebaut.

import {app, element, millisecondsSinceSessionRefresh, refreshSession, request, setCsrfToken, setUnauthenticatedHandler, toast} from '../core.js';
import {emptyState, whileNavigating} from './ui.js';
import {moduleIcon} from './icons.js';
import {hasModule, isGlobalAdministrator, setSessionState} from './session.js';
import {clearPinSessionUnlocks, ensurePinUnlocked} from './pin.js';
import {renderLogin} from './auth.js';
import {showOverview} from './overview.js';
import {showPages} from './pages.js';
import {showMembershipManagement} from './members.js';
import {showEventManagement} from './events.js';
import {showContactFeedbackManagement} from './contact.js';
import {showSettingsManagement} from './settings.js';
import {showRentalManagement} from './rental.js';
import {showPhotoManagement} from './photos.js';
import {showMediaLibrary} from './media.js';

let workspace = null;

const adminPath = (...segments) => `/admin/${segments.filter(Boolean).map(encodeURIComponent).join('/')}`;
const currentAdminSegments = () => window.location.pathname
    .replace(/^\/admin\/?/, '')
    .split('/')
    .filter(Boolean)
    .map((segment) => decodeURIComponent(segment));
const setAdminPath = (segments, replace = false) => {
    const path = adminPath(...segments);
    if (window.location.pathname === path) return;
    window.history[replace ? 'replaceState' : 'pushState']({}, '', path);
};

// Sitzungs-Timer für die Redaktion (siehe `SessionTokenIssuer`, `AuthenticationController`): ein
// Inaktivitäts-Logout nach 60 Minuten ohne Interaktion — unabhängig vom Token-Status — sowie ein
// proaktiver Refresh alle 10 Minuten, solange der Tab offen und die Person aktiv ist.
const SESSION_IDLE_TIMEOUT_MS = 60 * 60 * 1000;
const SESSION_REFRESH_INTERVAL_MS = 10 * 60 * 1000;
const SESSION_ACTIVITY_EVENTS = ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'];
let sessionIdleTimer = null;
const SIDEBAR_STORAGE_KEY = 'waldbad.admin.sidebar';
let sessionRefreshTimer = null;

const resetSessionIdleTimer = () => {
    if (sessionIdleTimer) clearTimeout(sessionIdleTimer);
    sessionIdleTimer = setTimeout(() => {
        request('/api/auth/v1/logout', {method: 'POST', body: JSON.stringify({reason: 'inactivity'})}).catch(() => {});
        forceLogout('Du wurdest wegen Inaktivität abgemeldet.');
    }, SESSION_IDLE_TIMEOUT_MS);
};

// Gedrosselte oder eingefrorene Hintergrund-Tabs holen den verpassten Refresh nach, sobald sie
// wieder sichtbar werden, statt erst beim nächsten Request in ein abgelaufenes Access-Token zu laufen.
const refreshSessionWhenVisible = () => {
    if (document.visibilityState !== 'visible' || millisecondsSinceSessionRefresh() < SESSION_REFRESH_INTERVAL_MS) return;
    refreshSession().catch(() => {});
};

const stopSessionTimers = () => {
    if (sessionIdleTimer) clearTimeout(sessionIdleTimer);
    if (sessionRefreshTimer) clearInterval(sessionRefreshTimer);
    sessionIdleTimer = null;
    sessionRefreshTimer = null;
    SESSION_ACTIVITY_EVENTS.forEach((eventName) => document.removeEventListener(eventName, resetSessionIdleTimer));
    document.removeEventListener('visibilitychange', refreshSessionWhenVisible);
};

const startSessionTimers = () => {
    stopSessionTimers();
    resetSessionIdleTimer();
    SESSION_ACTIVITY_EVENTS.forEach((eventName) => document.addEventListener(eventName, resetSessionIdleTimer, {passive: true}));
    document.addEventListener('visibilitychange', refreshSessionWhenVisible);
    sessionRefreshTimer = setInterval(() => {
        // Ein Fehlschlag hier (z. B. weil der Refresh-Token abgelaufen ist) landet als 401 im
        // `request()`-Wrapper, der die Sitzung dann selbst beendet (siehe `setUnauthenticatedHandler`
        // unten) — hier reicht es, die sonst unbehandelte Promise-Ablehnung abzufangen.
        refreshSession().catch(() => {});
    }, SESSION_REFRESH_INTERVAL_MS);
};

/**
 * Beendet die Redaktions-Sitzung ausschließlich clientseitig (Timer stoppen, Sitzungszustand
 * zurücksetzen, Anmeldeseite zeigen) — der Server-Logout-Aufruf selbst obliegt dem jeweiligen
 * Aufrufer (siehe Logout-Button, Inaktivitäts-Timer, `core.js`s `request()`).
 */
const forceLogout = (message) => {
    stopSessionTimers();
    setCsrfToken(null);
    setSessionState(null);
    clearPinSessionUnlocks();
    window.onpopstate = null;
    if (message) toast(message, 'info');
    renderLogin();
};
setUnauthenticatedHandler(forceLogout);

/**
 * Baut Seitenleiste, Kopfzeile und Routing auf und zeigt den zur aktuellen URL passenden Bereich —
 * aufgerufen aus `admin.js`, sobald Login-Token-Einlösung bzw. bestehende Sitzung bestätigt sind.
 */
const mountAdminShell = async (session) => {
    setCsrfToken(session.csrfToken);
    setSessionState(session.user);
    startSessionTimers();

    workspace = element('section', {className: 'admin-workspace'});
    const sidebarTitle = element('h1', {text: 'Redaktion'});
    const menu = element('nav', {className: 'admin-menu', attributes: {id: 'admin-navigation-menu', 'aria-label': 'Redaktionsbereiche'}});
    const sidebarToggle = element('button', {className: 'admin-sidebar-toggle', attributes: {type: 'button', 'aria-controls': 'admin-navigation'}});
    const sidebar = element('aside', {className: 'admin-sidebar', attributes: {id: 'admin-navigation'}, children: [
        element('div', {className: 'admin-sidebar-inner', children: [
            element('div', {className: 'admin-sidebar-heading', children: [sidebarToggle, sidebarTitle]}),
            menu,
        ]}),
    ]});
    const adminLayout = element('main', {className: 'admin-layout', children: [sidebar, workspace]});
    // Auf dem Desktop ist die Seitenleiste standardmäßig auf Icons eingeklappt; die Wahl gilt nur
    // für diesen Browser.
    const setSidebarCollapsed = (collapsed) => {
        adminLayout.classList.toggle('admin-sidebar-collapsed', collapsed);
        sidebarToggle.textContent = collapsed ? '»' : '«';
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Seitenleiste ausklappen' : 'Seitenleiste einklappen');
        sidebarToggle.title = collapsed ? 'Seitenleiste ausklappen' : 'Seitenleiste einklappen';
    };
    let sidebarCollapsed = true;
    try {
        sidebarCollapsed = window.localStorage.getItem(SIDEBAR_STORAGE_KEY) !== 'expanded';
    } catch {
        // Ohne Speicherzugriff (z. B. privates Fenster) bleibt es beim eingeklappten Standard.
    }
    setSidebarCollapsed(sidebarCollapsed);
    sidebarToggle.addEventListener('click', () => {
        const collapsed = !adminLayout.classList.contains('admin-sidebar-collapsed');
        setSidebarCollapsed(collapsed);
        try {
            window.localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? 'collapsed' : 'expanded');
        } catch {
            // Die Wahl gilt dann nur bis zum Neuladen.
        }
    });
    const navigationToggle = element('button', {
        className: 'admin-nav-toggle',
        attributes: {
            type: 'button',
            'aria-controls': 'admin-navigation',
            'aria-expanded': 'false',
            'aria-label': 'Redaktionsnavigation öffnen',
        },
        children: [
            element('span', {className: 'admin-nav-toggle-bar'}),
            element('span', {className: 'admin-nav-toggle-bar'}),
            element('span', {className: 'admin-nav-toggle-bar'}),
        ],
    });
    const closeAdminNavigation = () => {
        adminLayout.classList.remove('admin-nav-open');
        navigationToggle.setAttribute('aria-expanded', 'false');
        navigationToggle.setAttribute('aria-label', 'Redaktionsnavigation öffnen');
    };
    navigationToggle.addEventListener('click', () => {
        const open = adminLayout.classList.toggle('admin-nav-open');
        navigationToggle.setAttribute('aria-expanded', String(open));
        navigationToggle.setAttribute('aria-label', open ? 'Redaktionsnavigation schließen' : 'Redaktionsnavigation öffnen');
    });
    workspace.addEventListener('click', closeAdminNavigation);

    const initialAdminSegments = currentAdminSegments();

    const activateMenu = async (item, segments, updateHistory = false) => {
        if (updateHistory) setAdminPath(segments);
        menu.querySelectorAll('.admin-menu-item').forEach((menuItem) => menuItem.classList.remove('active'));
        item.link.classList.add('active');
        item.link.setAttribute('aria-current', 'page');
        menu.querySelectorAll('.admin-menu-item:not(.active)').forEach((menuItem) => menuItem.removeAttribute('aria-current'));
        closeAdminNavigation();
        workspace.scrollTop = 0;
        try {
            await whileNavigating(() => item.action(segments));
        } catch (error) {
            toast(error.message, 'error');
            workspace.replaceChildren(emptyState(error.message));
        }
    };
    const addMenu = (label, icon, slug, defaultSegments, action) => {
        const link = element('a', {
            className: 'admin-menu-item',
            attributes: {href: adminPath(...defaultSegments), title: label},
            children: [
                moduleIcon(icon),
                element('span', {className: 'admin-menu-label', text: label}),
            ],
        });
        const item = {slug, defaultSegments, action, link};
        link.addEventListener('click', async (event) => {
            event.preventDefault();
            await activateMenu(item, defaultSegments, true);
        });
        menu.append(link);

        return item;
    };
    const menuItems = [];
    menuItems.push(addMenu('Übersicht', 'overview', 'uebersicht', ['uebersicht'], showOverview));
    if (hasModule('pages')) menuItems.push(addMenu('Seiten', 'pages', 'seiten', ['seiten'], showPages));

    if (hasModule('events') || hasModule('event_helpers') || hasModule('activities')) {
        const defaultEventSlug = hasModule('events') ? 'termine' : hasModule('event_helpers') ? 'helfer' : 'aktivitaeten';
        menuItems.push(addMenu('Veranstaltung', 'events', 'veranstaltungen', ['veranstaltungen', defaultEventSlug], showEventManagement));
    }

    if (hasModule('members') || hasModule('contribution_rates') || hasModule('membership_applications') || hasModule('member_messages')) {
        const defaultMembershipSlug = hasModule('members')
            ? 'dashboard'
            : hasModule('contribution_rates')
                ? 'beitragssaetze'
                : hasModule('membership_applications') ? 'antraege' : 'nachrichten';
        menuItems.push(addMenu('Mitgliederverwaltung', 'members', 'mitglieder', ['mitglieder', defaultMembershipSlug], async (segments) => {
            if (!(await ensurePinUnlocked('members.module_access', 'Mitgliederverwaltung'))) return;
            await showMembershipManagement(segments);
        }));
    }

    if (hasModule('photos')) {
        menuItems.push(addMenu('Fotos', 'photos', 'fotos', ['fotos'], showPhotoManagement));
    }

    if (hasModule('media_library')) {
        menuItems.push(addMenu('Mediathek', 'media', 'mediathek', ['mediathek'], showMediaLibrary));
    }

    if (hasModule('rental_sauna')) {
        menuItems.push(addMenu('Vermietung', 'rental', 'vermietung', ['vermietung', 'sauna', 'anmeldungen'], showRentalManagement));
    }

    if (hasModule('contact_requests') || hasModule('guestbook')) {
        const defaultContactSlug = hasModule('contact_requests') ? 'kontaktanfragen' : 'gaestebuch';
        menuItems.push(addMenu('Kontakt und Feedback', 'contact', 'kontakt-feedback', ['kontakt-feedback', defaultContactSlug], showContactFeedbackManagement));
    }

    if (hasModule('user_management') || isGlobalAdministrator()) {
        const defaultSettingsSlug = hasModule('user_management') ? 'benutzer' : 'pin-schutz';
        menuItems.push(addMenu('Einstellungen', 'settings', 'einstellungen', ['einstellungen', defaultSettingsSlug], showSettingsManagement));
    }

    // Anders als früher kennt diese Funktion die Unter-Reiter der einzelnen Bereiche nicht mehr
    // (kein `membershipTabsBySlug` & Co. auf Shell-Ebene) — ist die URL für den getroffenen
    // Bereich ungültig (z. B. ein unbekannter Unter-Reiter), erkennt und korrigiert das der Bereich
    // selbst (siehe z. B. `showMembershipManagement`, das dabei ebenfalls die URL berichtigt).
    const openAdminRoute = async (segments, replaceInvalid = false) => {
        const item = menuItems.find((menuItem) => menuItem.slug === segments[0]) || menuItems[0];
        if (!item) {
            workspace.replaceChildren(emptyState('Für diesen Zugang ist kein Redaktionsmodul freigeschaltet.'));
            return;
        }
        const requestedSegments = item.slug === segments[0] ? segments : item.defaultSegments;
        if (replaceInvalid || requestedSegments !== segments) setAdminPath(requestedSegments, true);
        await activateMenu(item, requestedSegments);
    };

    const logout = element('button', {className: 'text-button', text: 'Abmelden', attributes: {type: 'button'}});
    logout.addEventListener('click', async () => {
        await request('/api/auth/v1/logout', {method: 'POST'});
        forceLogout('Du wurdest abgemeldet.');
    });

    app.replaceChildren(
        element('header', {className: 'admin-header', children: [
            element('a', {className: 'admin-brand', text: 'Waldbad · Redaktion', attributes: {href: '/admin'}}),
            navigationToggle,
            element('div', {className: 'admin-account', children: [
                element('span', {text: session.user.displayName}),
                logout,
            ]}),
        ]}),
        adminLayout,
    );
    app.onkeydown = (event) => {
        if (event.key === 'Escape' && adminLayout.classList.contains('admin-nav-open')) {
            closeAdminNavigation();
            navigationToggle.focus();
        }
    };
    window.onpopstate = () => openAdminRoute(currentAdminSegments());
    await openAdminRoute(initialAdminSegments, initialAdminSegments.length === 0);
};

export {workspace, adminPath, setAdminPath, forceLogout, mountAdminShell};
