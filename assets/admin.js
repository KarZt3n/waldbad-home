// Entrypoint für das Redaktions-Backend (siehe `templates/site/admin.html.twig`,
// `importmap.php`). Übernimmt nur den Login-/Sitzungsaufbau — der eigentliche Rahmen (Menü,
// Routing, Sitzungs-Timer) lebt in `admin/shell.js`, die Bereiche selbst in den übrigen
// `admin/*.js`-Modulen.

import './styles/app.css';
import {app, renderError, request} from './core.js';
import {renderLogin} from './admin/auth.js';
import {mountAdminShell} from './admin/shell.js';

const renderAdmin = async () => {
    // Ein Anmeldelink landet hier als `?login_token=…` (siehe `LoginLinkBuilder`) — wird sofort
    // eingelöst, bevor überhaupt geprüft wird, ob schon eine Sitzung besteht.
    const loginToken = new URLSearchParams(window.location.search).get('login_token');
    let session;
    if (loginToken !== null) {
        try {
            session = await request('/api/auth/v1/login', {method: 'POST', body: JSON.stringify({token: loginToken})});
        } catch (error) {
            renderLogin(error.message);
            return;
        } finally {
            window.history.replaceState(null, '', window.location.pathname);
        }
    } else {
        try {
            session = await request('/api/auth/v1/me');
        } catch {
            renderLogin();
            return;
        }
    }

    await mountAdminShell(session);
};

if (app?.dataset.app === 'admin') renderAdmin().catch((error) => renderError(error.message));
