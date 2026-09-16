// Kopf-/Fußzeile der öffentlichen Website sowie das Mitgliedschafts-Pulldown darin — gemeinsam
// genutzt vom Haupt-Entrypoint (`public.js`, normale CMS-Seiten) und von der eigenständigen
// „Meine Mitgliedschaft“-Ansicht (`member-self-service.js`), daher als eigenes Modul statt in einem
// der beiden, um einen gegenseitigen Import zu vermeiden.

import {element, pageHref, treeContainsSlug} from '../core.js';

// Fester Slug der eigenständigen Route `/meine-mitgliedschaft` (siehe `FrontendController`) — keine
// CMS-Seite, die Ansicht hängt nicht von redaktionell gepflegtem Inhalt ab (siehe `renderPublic`,
// `renderMemberSelfServicePage`).
const MEMBER_ACCESS_SLUG = 'meine-mitgliedschaft';
/**
 * Baut die Kopfzeile inkl. Hauptnavigation, verwendet von `renderPublic` sowohl für normale
 * CMS-Seiten als auch für die eigenständige „Meine Mitgliedschaft"-Ansicht — `extraChildren` hängt
 * zusätzliche Elemente rechts neben die Navigation (siehe `buildMemberAccessNav`).
 */
const buildSiteHeader = (navigationTree, activeSlug, extraChildren = []) => {
    const renderNavigationItem = (item, nested = false) => {
        const active = treeContainsSlug(item, activeSlug);
        const link = element('a', {
            className: item.slug === activeSlug ? 'active' : '',
            text: item.label,
            attributes: {
                href: pageHref(item.slug),
                ...(item.slug === activeSlug ? {'aria-current': 'page'} : {}),
            },
        });
        if (!item.children.length) return nested ? link : element('div', {className: 'main-nav-item', children: [link]});

        const toggle = element('button', {
            className: 'submenu-toggle',
            attributes: {type: 'button', 'aria-label': `Unterseiten von ${item.label} anzeigen`, 'aria-expanded': 'false'},
        });
        const container = element('div', {
            className: `main-nav-item has-children${active ? ' active-branch' : ''}`,
            children: [
                link,
                toggle,
                element('div', {className: 'submenu', children: item.children.map((child) => renderNavigationItem(child, true))}),
            ],
        });
        toggle.addEventListener('click', () => {
            const open = container.classList.toggle('submenu-open');
            toggle.setAttribute('aria-expanded', String(open));
        });

        return container;
    };
    const links = navigationTree.map((item) => renderNavigationItem(item));

    const mainNav = element('nav', {
        className: 'main-nav',
        attributes: {id: 'main-nav', 'aria-label': 'Hauptnavigation'},
        children: links,
    });
    const navToggle = element('button', {
        className: 'nav-toggle',
        attributes: {type: 'button', 'aria-controls': 'main-nav', 'aria-expanded': 'false', 'aria-label': 'Menü öffnen'},
        children: [
            element('span', {className: 'nav-toggle-bar'}),
            element('span', {className: 'nav-toggle-bar'}),
            element('span', {className: 'nav-toggle-bar'}),
        ],
    });
    const header = element('header', {
        className: 'site-header',
        children: [
            element('a', {
                className: 'brand',
                attributes: {href: '/', 'aria-label': 'Waldbad Borkheide – Startseite'},
                children: [
                    element('img', {
                        className: 'brand-logo',
                        attributes: {
                            src: '/downloads/waldbad-borkheide-logo.svg',
                            alt: '',
                            width: '96',
                            height: '72',
                            fetchpriority: 'high',
                        },
                    }),
                    element('span', {children: [
                        element('strong', {text: 'Waldbad Borkheide'}),
                        element('small', {text: '… natürlich baden!'}),
                    ]}),
                ],
            }),
            navToggle,
            mainNav,
            ...extraChildren,
        ],
    });
    const closeNav = () => {
        header.classList.remove('nav-open');
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.setAttribute('aria-label', 'Menü öffnen');
    };
    navToggle.addEventListener('click', () => {
        const open = header.classList.toggle('nav-open');
        navToggle.setAttribute('aria-expanded', String(open));
        navToggle.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
    });
    mainNav.addEventListener('click', (event) => {
        if (event.target.closest('a')) closeNav();
    });
    document.addEventListener('click', (event) => {
        if (header.classList.contains('nav-open') && !header.contains(event.target)) closeNav();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && header.classList.contains('nav-open')) closeNav();
    });

    return header;
};
const buildSiteFooter = () => element('footer', {
    className: 'site-footer',
    children: [
        element('p', {text: '© ' + new Date().getFullYear() + ' Naturbad Borkheide e.V.'}),
        element('nav', {attributes: {'aria-label': 'Servicenavigation'}, children: [
            element('a', {text: 'Impressum', attributes: {href: '/seite/impressum'}}),
            element('a', {text: 'Kontakt', attributes: {href: '/seite/kontakt'}}),
            element('a', {text: 'Gästebuch', attributes: {href: '/seite/gaestebuch'}}),
            element('a', {text: 'Unterstützer', attributes: {href: '/seite/unterstuetzer'}}),
            element('a', {text: 'Redaktion', attributes: {href: '/admin'}}),
        ]}),
    ],
});
/**
 * Icon ganz rechts in der Kopfzeile (siehe `buildSiteHeader`) — bei Klick öffnet sich ein Pulldown
 * mit dem Link zu „Meine Mitgliedschaft" (`/meine-mitgliedschaft`, siehe `MEMBER_ACCESS_SLUG`,
 * `renderMemberSelfServicePage`).
 */
const buildMemberAccessNav = () => {
    const icon = element('span', {className: 'member-access-icon', attributes: {'aria-hidden': 'true'}});
    icon.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"></path></svg>';
    const toggle = element('button', {
        className: 'member-access-toggle',
        attributes: {
            type: 'button',
            'aria-haspopup': 'true',
            'aria-expanded': 'false',
            'aria-label': 'Mitgliedschaftsmenü öffnen',
        },
    });
    toggle.append(icon);
    const menu = element('div', {className: 'member-access-menu', children: [
        element('a', {text: 'Meine Mitgliedschaft', attributes: {href: '/' + MEMBER_ACCESS_SLUG}}),
    ]});
    const container = element('div', {className: 'member-access-nav', children: [toggle, menu]});
    const close = () => {
        container.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Mitgliedschaftsmenü öffnen');
    };
    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        const open = container.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Mitgliedschaftsmenü schließen' : 'Mitgliedschaftsmenü öffnen');
    });
    document.addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });

    return container;
};

export {MEMBER_ACCESS_SLUG, buildSiteHeader, buildSiteFooter, buildMemberAccessNav};
