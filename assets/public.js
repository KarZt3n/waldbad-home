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
const renderPublic = async () => {
    try {
        const slug = app.dataset.pageSlug;
        const navigation = await request('/api/public/v1/navigation');
        const navigationTree = buildPageTree(navigation.items, false);

        if (slug === MEMBER_ACCESS_SLUG) {
            await renderMemberSelfServicePage(navigationTree);
            return;
        }

        const page = await request('/api/public/v1/pages/' + encodePageSlug(slug));
        updateDocumentMetadata(page);

        const publicContext = {visited: new Set([page.id]), pagesById: null, showEmbedErrors: false, isPreview: false};
        const article = renderContentCard(page, publicContext);
        if (slug === 'kontakt') article.append(renderContactForm());
        if (slug === 'gaestebuch') article.append(await renderGuestbook());

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
    } catch (error) {
        renderError(error.message);
    }
};

if (app?.dataset.app === 'public') renderPublic();
