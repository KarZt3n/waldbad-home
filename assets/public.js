// Entrypoint für die öffentliche Website (siehe `templates/site/public.html.twig`,
// `importmap.php`). Bündelt nur, was öffentliche Seiten tatsächlich brauchen — kein
// Redaktions-Code (siehe `admin.js` für das Backend).

import './styles/app.css';
import {app, buildPageTree, element, encodePageSlug, renderError, request} from './core.js';
import {renderContentCard} from './content-rendering.js';
import {renderContactForm} from './public/contact.js';
import {renderGuestbook} from './public/guestbook.js';
import {buildMemberAccessNav, buildSiteFooter, buildSiteHeader, MEMBER_ACCESS_SLUG} from './public/site-chrome.js';
import {renderMemberSelfServicePage} from './public/member-self-service.js';
import {EMAIL_CONSENT_SLUG, renderEmailConsentPage} from './public/email-consent.js';

const updateDocumentMetadata = (page) => {
    document.title = `${page.seoTitle || page.title} – Waldbad Borkheide`;
    let description = document.querySelector('meta[name="description"]');
    if (!description) {
        description = document.createElement('meta');
        description.setAttribute('name', 'description');
        document.head.append(description);
    }
    description.setAttribute('content', page.seoDescription || 'Natürlich baden ohne Chlor im Waldbad Borkheide.');
};
// Die Navigation ändert sich während eines Besuchs praktisch nie; sie wird deshalb nur einmal geladen
// und bei jedem Seitenwechsel wiederverwendet.
let navigationTreeRequest = null;
const loadNavigationTree = () => {
    navigationTreeRequest ??= request('/api/public/v1/navigation')
        .then((navigation) => buildPageTree(navigation.items, false))
        .catch((error) => {
            navigationTreeRequest = null;
            throw error;
        });

    return navigationTreeRequest;
};

/**
 * Lädt alle Daten einer Seite und liefert eine Funktion, die sie anschließend ohne weitere Wartezeit
 * einsetzt. So bleibt beim Seitenwechsel die bisherige Seite stehen, bis die neue vollständig da ist.
 */
const preparePublicPage = async (slug) => {
    const navigationTree = await loadNavigationTree();

    if (slug === MEMBER_ACCESS_SLUG) return () => renderMemberSelfServicePage(navigationTree);
    if (slug === EMAIL_CONSENT_SLUG) return () => renderEmailConsentPage(navigationTree);

    const page = await request('/api/public/v1/pages/' + encodePageSlug(slug));
    const publicContext = {visited: new Set([page.id]), pagesById: null, showEmbedErrors: false, isPreview: false};
    const article = renderContentCard(page, publicContext);
    if (slug === 'kontakt') article.append(renderContactForm());
    if (slug === 'gaestebuch') article.append(await renderGuestbook());

    return () => {
        updateDocumentMetadata(page);
        app.replaceChildren(
            buildSiteHeader(navigationTree, slug, [buildMemberAccessNav()]),
            element('main', {
                className: 'page-shell',
                children: [
                    element('section', {
                        className: 'page-hero',
                        children: [
                            element('p', {className: 'eyebrow', text: 'Naturbad · Borkheide'}),
                            element('h1', {text: page.title}),
                            ...(page.seoDescription ? [element('p', {className: 'lead', text: page.seoDescription})] : []),
                        ],
                    }),
                    article,
                ],
            }),
            buildSiteFooter(),
        );
    };
};

const pageSlugFromUrl = (url) => {
    if (url.origin !== window.location.origin) return null;
    if (url.pathname === '/') return 'startseite';
    if (url.pathname === '/' + MEMBER_ACCESS_SLUG) return MEMBER_ACCESS_SLUG;
    if (url.pathname === '/' + EMAIL_CONSENT_SLUG) return EMAIL_CONSENT_SLUG;
    if (!url.pathname.startsWith('/seite/')) return null;
    try {
        return url.pathname.slice('/seite/'.length).split('/').map((segment) => decodeURIComponent(segment)).join('/') || null;
    } catch {
        return null;
    }
};

// Beim Zurückspringen ändert sich die Höhe der Seite nach dem Einsetzen noch (Bilder und nachgeladene
// Blöcke bekommen ihre Höhe erst später, der Browser verschiebt dabei die Scrollposition selbst). Die
// Position wird deshalb kurz festgehalten – es sei denn, der Besucher scrollt inzwischen selbst.
const restoreScrollPosition = (scrollY) => {
    window.scrollTo(0, scrollY);
    const stop = new AbortController();
    const observer = new ResizeObserver(() => window.scrollTo(0, scrollY));
    const finish = () => {
        observer.disconnect();
        stop.abort();
    };
    observer.observe(document.body);
    ['wheel', 'touchstart', 'keydown'].forEach((type) => window.addEventListener(type, finish, {signal: stop.signal, passive: true}));
    setTimeout(finish, 3000);
};
const scrollAfterNavigation = (url, scrollY) => {
    const target = url.hash ? document.getElementById(decodeURIComponent(url.hash.slice(1))) : null;
    if (target) target.scrollIntoView();
    else restoreScrollPosition(scrollY ?? 0);
};

// Nur der zuletzt angestoßene Seitenwechsel darf die Seite austauschen; ältere, langsamere Antworten
// bei schnellem Weiterklicken werden verworfen.
let latestNavigation = 0;
const pathOf = (url) => url.pathname + url.search;
let currentPath = pathOf(window.location);
const navigateTo = async (url, {push, scrollY = null}) => {
    const slug = pageSlugFromUrl(url);
    const navigation = ++latestNavigation;
    app.setAttribute('aria-busy', 'true');
    try {
        const show = await preparePublicPage(slug);
        if (navigation !== latestNavigation) return;
        if (push) {
            history.replaceState({scrollY: window.scrollY}, '');
            history.pushState({scrollY: 0}, '', url.href);
        }
        document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
        await show();
        currentPath = pathOf(url);
        scrollAfterNavigation(url, scrollY);
    } catch {
        // Ein Fehler (z. B. eine gelöschte Seite) wird wie beim direkten Aufruf vom Server dargestellt.
        if (navigation === latestNavigation) window.location.assign(url.href);
    } finally {
        if (navigation === latestNavigation) app.removeAttribute('aria-busy');
    }
};

const startClientNavigation = () => {
    // Beim Zurückspringen stellt `navigateTo` die Position erst her, wenn die Seite wieder steht.
    history.scrollRestoration = 'manual';
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;
        const url = new URL(link.href, window.location.href);
        if (pageSlugFromUrl(url) === null) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
        event.preventDefault();
        navigateTo(url, {push: true});
    });
    window.addEventListener('popstate', (event) => {
        const url = new URL(window.location.href);
        // Zurück zwischen Sprungmarken derselben Seite erledigt der Browser selbst.
        if (pageSlugFromUrl(url) === null || pathOf(url) === currentPath) return;
        navigateTo(url, {push: false, scrollY: event.state?.scrollY ?? 0});
    });
};

if (app?.dataset.app === 'public') {
    startClientNavigation();
    preparePublicPage(app.dataset.pageSlug)
        .then((show) => show())
        .catch((error) => renderError(error.message));
}
