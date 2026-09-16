// Anmeldeseite der Redaktion (siehe `admin.js` fürs Einlösen des Anmeldelinks selbst).

import {app, element, field, request, toast} from '../core.js';

/**
 * Passwortlose Anmeldung: nur die E-Mail-Adresse anfordern, der eigentliche Anmeldelink kommt per
 * Mail (siehe `RequestLoginUseCase`) — bewusst immer dieselbe Erfolgsmeldung, unabhängig davon, ob
 * die E-Mail-Adresse zu einem Redaktions-Benutzer gehört (vgl. `renderMemberAccessRequestForm`).
 * Der Link selbst wird nicht hier, sondern beim Laden von `renderAdmin()` eingelöst (siehe dort,
 * `login_token`-Query-Parameter).
 */
const renderLogin = (initialMessage) => {
    app.onkeydown = null;
    const message = element('p', {className: 'form-message', attributes: {'aria-live': 'polite'}});
    if (initialMessage) message.textContent = initialMessage;
    const emailField = field('E-Mail-Adresse', 'email', '', 'email');
    const form = element('form', {
        className: 'login-card',
        children: [
            element('p', {className: 'eyebrow', text: 'Waldbad Borkheide'}),
            element('h1', {text: 'Redaktion'}),
            element('p', {text: 'Trage deine E-Mail-Adresse ein. Du erhältst per Mail einen Anmeldelink, der 30 Minuten gültig ist.'}),
            emailField,
            message,
            element('button', {className: 'button', text: 'Anmeldelink anfordern', attributes: {type: 'submit'}}),
        ],
    });
    emailField.querySelector('input').required = true;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const result = await request('/api/auth/v1/login-requests', {
                method: 'POST',
                body: JSON.stringify({email: data.get('email')}),
            });
            form.reset();
            message.textContent = result.message;
            message.classList.add('success');
            toast(result.message);
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    });
    app.replaceChildren(element('main', {className: 'login-shell', children: [form]}));
};

export {renderLogin};
