// Bestätigungsseite für die E-Mail-Einwilligung (siehe `PublicEmailConsentController`,
// `MemberEmailConsentLinkBuilder`) — eigenständige Ansicht außerhalb des CMS-Seitenbaums, analog zu
// `member-self-service.js`. Bewusst ein eigener „Bestätigen“-Klick statt automatischer Bestätigung
// beim bloßen Öffnen der Seite: sonst könnte z. B. eine Link-Vorschau des E-Mail-Programms die
// Einwilligung ungewollt auslösen.

import {app, element, formMessage, request} from '../core.js';
import {buildSiteFooter, buildSiteHeader} from './site-chrome.js';

const EMAIL_CONSENT_SLUG = 'e-mail-einwilligung';

const renderEmailConsentConfirmation = (token) => {
    const message = formMessage();
    const confirm = element('button', {className: 'button', text: 'Einwilligung bestätigen', attributes: {type: 'button'}});
    const content = element('div', {className: 'public-form', children: [
        element('h2', {text: 'E-Mail-Einwilligung bestätigen'}),
        element('p', {text: 'Bitte bestätige, dass du künftig Vereinsinformationen per E-Mail erhalten möchtest.'}),
        message,
        confirm,
    ]});
    confirm.addEventListener('click', async () => {
        confirm.disabled = true;
        try {
            const result = await request('/api/public/v1/email-consent/confirmations', {method: 'POST', body: JSON.stringify({token})});
            message.textContent = result.message;
            message.classList.add('success');
            confirm.remove();
        } catch (error) {
            message.textContent = error.message;
            confirm.disabled = false;
        }
    });

    return content;
};

const renderEmailConsentMissingToken = () => element('div', {className: 'public-form', children: [
    element('h2', {text: 'E-Mail-Einwilligung bestätigen'}),
    element('p', {text: 'Dieser Link ist unvollständig. Bitte nutze den vollständigen Link aus der E-Mail.'}),
]});

const renderEmailConsentPage = async (navigationTree) => {
    const token = new URLSearchParams(window.location.search).get('token');
    const content = token ? renderEmailConsentConfirmation(token) : renderEmailConsentMissingToken();

    app.replaceChildren(
        buildSiteHeader(navigationTree, EMAIL_CONSENT_SLUG, []),
        element('main', {
            className: 'page-shell',
            children: [
                element('section', {
                    className: 'page-hero',
                    children: [
                        element('p', {className: 'eyebrow', text: 'Naturbad · Borkheide'}),
                        element('h1', {text: 'E-Mail-Einwilligung'}),
                    ],
                }),
                element('div', {className: 'content-card', children: [content]}),
            ],
        }),
        buildSiteFooter(),
    );
};

export {EMAIL_CONSENT_SLUG, renderEmailConsentPage};
