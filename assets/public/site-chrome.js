// Kopf-/Fußzeile der öffentlichen Website sowie das Mitgliedschafts-Pulldown darin — gemeinsam
// genutzt vom Haupt-Entrypoint (`public.js`, normale CMS-Seiten) und von der eigenständigen
// „Meine Mitgliedschaft“-Ansicht (`member-self-service.js`), daher als eigenes Modul statt in einem
// der beiden, um einen gegenseitigen Import zu vermeiden.

import {element, pageHref, request, treeContainsSlug} from '../core.js';

// Fester Slug der eigenständigen Route `/meine-mitgliedschaft` (siehe `FrontendController`) — keine
// CMS-Seite, die Ansicht hängt nicht von redaktionell gepflegtem Inhalt ab (siehe `preparePublicPage`,
// `renderMemberSelfServicePage`).
const MEMBER_ACCESS_SLUG = 'meine-mitgliedschaft';
// Beim Seitenwechsel ohne Neuladen (siehe `navigateTo` in `public.js`) entsteht die Kopfzeile jedes Mal
// neu; die Listener der vorherigen am `document` werden darüber wieder entfernt.
let headerListeners = new AbortController();
let memberAccessListeners = new AbortController();
let socialMediaListeners = new AbortController();
/**
 * Baut die Kopfzeile inkl. Hauptnavigation, verwendet von `preparePublicPage` sowohl für normale
 * CMS-Seiten als auch für die eigenständige „Meine Mitgliedschaft"-Ansicht — `extraChildren` hängt
 * zusätzliche Elemente rechts neben die Navigation (siehe `buildMemberAccessNav`).
 */
const buildSiteHeader = (navigationTree, activeSlug, extraChildren = []) => {
    headerListeners.abort();
    headerListeners = new AbortController();
    const {signal} = headerListeners;
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
    }, {signal});
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && header.classList.contains('nav-open')) closeNav();
    }, {signal});

    return header;
};
// Icons der Social-Media-Leiste je `SocialPlatform` (Backend); Plattformen ohne Icon werden nicht angezeigt.
const SOCIAL_MEDIA_ICONS = {
    instagram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r=".6" fill="currentColor"></circle></svg>',
    facebook: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 8.6c0-.4.3-.6.6-.6H17V4h-3c-2.8 0-4 1.8-4 4.3V10H7.5v4H10v8h4v-8h2.6l.6-4H14z"></path></svg>',
    youtube: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M21.6 7.2a2.6 2.6 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4a2.6 2.6 0 0 0-1.8 1.8C2 8.8 2 12 2 12s0 3.2.4 4.8a2.6 2.6 0 0 0 1.8 1.8c1.6.4 7.8.4 7.8.4s6.2 0 7.8-.4a2.6 2.6 0 0 0 1.8-1.8c.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8zM10 15.1V8.9l5.2 3.1z"></path></svg>',
    tiktok: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 3c.4 2.2 1.8 3.7 4 4v3.3a7.6 7.6 0 0 1-4-1.3v6.3A6.3 6.3 0 1 1 10.3 9v3.4a3 3 0 1 0 2.9 2.9V3z"></path></svg>',
    whatsapp: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21l1.7-4.6A8.6 8.6 0 1 1 8 19.6z"></path><path d="M9 8.5c0 3.5 3 6.5 6.5 6.5l1-1.6-2-1-1 .9a5 5 0 0 1-2.8-2.8l.9-1-1-2z"></path></svg>',
};
// Wie die Navigation ändert sich die Leiste während eines Besuchs praktisch nie und wird nur einmal geladen.
let socialMediaRequest = null;
const loadSocialMediaLinks = () => {
    socialMediaRequest ??= request('/api/public/v1/social-media')
        .then((data) => data.items.filter((link) => SOCIAL_MEDIA_ICONS[link.platform]))
        .catch(() => {
            socialMediaRequest = null;
            return [];
        });

    return socialMediaRequest;
};
// Liefert die Farben eines Hintergrunds als [r, g, b, a]; Verläufe (z. B. `.page-hero`) zählen über
// ihre einzelnen Farbstopps, Hintergrundbilder gelten als dunkel.
const backgroundColors = (style) => {
    if (style.backgroundImage.includes('url(')) return [[0, 0, 0, 1]];
    return [...`${style.backgroundImage} ${style.backgroundColor}`.matchAll(/rgba?\(([^)]+)\)/g)]
        .map((match) => match[1].split(',').map(Number))
        .map(([r, g, b, a = 1]) => [r, g, b, a])
        .filter(([, , , a]) => a >= .5);
};
const isDarkAt = (x, y, ignore) => {
    for (const node of document.elementsFromPoint(x, y)) {
        if (ignore.contains(node)) continue;
        const colors = backgroundColors(getComputedStyle(node));
        if (!colors.length) continue;
        const luminance = colors.reduce((sum, [r, g, b]) => sum + (.2126 * r + .7152 * g + .0722 * b), 0) / colors.length;

        return luminance < 140;
    }

    return false;
};
/**
 * Leiste mit den unter „Seiten“ → „Social Media“ gepflegten Links, als letztes Element in `<main>`
 * eingehängt: Per `position: sticky` (siehe CSS) bleibt sie am unteren Bildschirmrand und endet von
 * selbst über dem Footer, ohne dass ihre Position per Skript nachgeführt werden muss. Jedes Icon prüft
 * beim Scrollen den Hintergrund dahinter und wird auf dunklen Flächen weiß (`.on-dark`), sonst grün.
 * Wird nachgeladen, damit sie den Seitenaufbau nicht verzögert; ohne Links bleibt sie leer.
 */
const buildSocialMediaBar = () => {
    socialMediaListeners.abort();
    socialMediaListeners = new AbortController();
    const {signal} = socialMediaListeners;
    const bar = element('aside', {className: 'social-button-cont', attributes: {'aria-label': 'Social Media'}});
    let scheduled = false;
    const updateContrast = () => {
        scheduled = false;
        if (!bar.isConnected) return;
        bar.querySelectorAll('.social-button').forEach((button) => {
            const rect = button.getBoundingClientRect();
            button.classList.toggle('on-dark', isDarkAt(rect.left + rect.width / 2, rect.top + rect.height / 2, bar));
        });
    };
    const scheduleContrast = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(updateContrast);
    };
    window.addEventListener('scroll', scheduleContrast, {signal, passive: true});
    window.addEventListener('resize', scheduleContrast, {signal});
    loadSocialMediaLinks().then((links) => {
        bar.replaceChildren(...links.map((link) => {
            const anchor = element('a', {
                className: `social-button social-button-${link.platform}`,
                attributes: {href: link.url, target: '_blank', rel: 'noopener noreferrer', 'aria-label': `${link.label} (öffnet in neuem Tab)`, title: link.label},
            });
            anchor.innerHTML = SOCIAL_MEDIA_ICONS[link.platform];

            return anchor;
        }));
        // Die Leiste wird vor dem Einsetzen der Seite gebaut; Bilder bekommen ihre Höhe erst später.
        scheduleContrast();
        setTimeout(scheduleContrast, 500);
    });

    return bar;
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
    memberAccessListeners.abort();
    memberAccessListeners = new AbortController();
    const {signal} = memberAccessListeners;
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
    document.addEventListener('click', close, {signal});
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    }, {signal});

    return container;
};

export {MEMBER_ACCESS_SLUG, buildSiteHeader, buildSiteFooter, buildMemberAccessNav, buildSocialMediaBar};
