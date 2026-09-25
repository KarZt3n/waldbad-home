// Modul „Einstellungen": Benutzerverwaltung, PIN-Schutzverwaltung und E-Mail-Einstellungen
// (Verbindung/Mailvorlagen/Signaturen als weitere Unter-Reiter-Ebene) unter einer gemeinsamen Route
// (`/admin/einstellungen/...`).

import {
    buildPageTree, confirmAction, element, field, fieldRow, flattenPageTree, formMessage,
    request, selectField, toast,
} from '../core.js';
import {emptyState, sectionHeading} from './ui.js';
import {canEditModule, hasAnyRole, hasModule, isGlobalAdministrator} from './session.js';
import {promptForPin} from './pin.js';
import {adminPath, setAdminPath, workspace} from './shell.js';

const CMS_MODULES = [
    ['pages', 'Seiten'],
    ['events', 'Veranstaltungen'],
    ['activities', 'Aktivitäten'],
    ['guestbook', 'Gästebuch'],
    ['contact_requests', 'Kontaktanfragen'],
    ['event_helpers', 'Veranstaltungshelfer'],
    ['membership_applications', 'Mitgliedsanträge'],
    ['members', 'Mitglieder'],
    ['contribution_rates', 'Beitragssätze'],
    ['user_management', 'Benutzerverwaltung'],
    ['member_messages', 'Mitgliedernachrichten'],
    ['rental_sauna', 'Vermietung: Sauna'],
];
const showPinProtection = async () => {
    const data = await request('/api/admin/v1/pin-settings');
    const message = formMessage();

    const pinField = field(data.globalPinIsSet ? 'Neuer globaler PIN (4–8 Ziffern)' : 'Globaler PIN (4–8 Ziffern)', 'settings-pin', '', 'password');
    const pinInput = pinField.querySelector('input');
    pinInput.inputMode = 'numeric';
    pinInput.autocomplete = 'off';
    pinInput.maxLength = 8;
    const setPin = element('button', {className: 'button', text: data.globalPinIsSet ? 'Globalen PIN ändern' : 'Globalen PIN festlegen', attributes: {type: 'button'}});
    setPin.addEventListener('click', async () => {
        const pin = pinInput.value.trim();
        if (!pin) return;
        setPin.disabled = true;
        try {
            await request('/api/admin/v1/pin-settings/pin', {method: 'PUT', body: JSON.stringify({pin})});
            toast(data.globalPinIsSet ? 'Der globale PIN wurde geändert.' : 'Der globale PIN wurde festgelegt.');
            await showPinProtection();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
            setPin.disabled = false;
        }
    });

    // Je Aktion eigener, vom globalen PIN abweichender PIN — optional; ohne eigenen PIN gilt
    // der globale (siehe `PinSettings::matchesPin()`). Klick öffnet denselben PIN-Dialog wie
    // die Ausführung geschützter Aktionen, hier aber zum *Festlegen* statt zum Prüfen.
    const setOwnPin = async (action) => {
        const unlocked = await promptForPin(
            action.hasOwnPin ? `Eigenen PIN für „${action.label}“ ändern` : `Eigenen PIN für „${action.label}“ festlegen`,
            'Gilt danach nur noch für diesen Eintrag, statt des globalen PIN.',
            (pin) => request(`/api/admin/v1/pin-settings/actions/${action.key}/pin`, {method: 'PUT', body: JSON.stringify({pin})}),
        );
        if (unlocked) {
            toast(`Eigener PIN für „${action.label}“ gespeichert.`);
            await showPinProtection();
        }
    };
    const clearOwnPin = async (action) => {
        const confirmed = await confirmAction(
            'Eigenen PIN entfernen',
            `„${action.label}“ verwendet danach wieder den globalen PIN.`,
            'Entfernen',
        );
        if (!confirmed) return;
        try {
            await request(`/api/admin/v1/pin-settings/actions/${action.key}/pin`, {method: 'DELETE'});
            toast(`Eigener PIN für „${action.label}“ entfernt.`);
            await showPinProtection();
        } catch (error) {
            toast(error.message, 'error');
        }
    };

    const entries = data.availableActions.map((action) => {
        const checkbox = element('input', {attributes: {type: 'checkbox', value: action.key}});
        checkbox.checked = data.protectedActions.includes(action.key);

        const ownPinButton = element('button', {className: 'secondary-button', text: action.hasOwnPin ? 'Eigenen PIN ändern' : 'Eigenen PIN festlegen', attributes: {type: 'button'}});
        ownPinButton.addEventListener('click', () => setOwnPin(action));
        const clearOwnPinButton = action.hasOwnPin
            ? element('button', {className: 'text-button', text: 'Eigenen PIN entfernen', attributes: {type: 'button'}})
            : null;
        if (clearOwnPinButton) clearOwnPinButton.addEventListener('click', () => clearOwnPin(action));

        const row = element('div', {className: 'protected-action-row', children: [
            element('label', {className: 'check-field', children: [checkbox, element('span', {text: action.label})]}),
            element('span', {className: 'field-hint', text: action.hasOwnPin ? 'Eigener PIN gesetzt' : 'Nutzt globalen PIN'}),
            ownPinButton,
            ...(clearOwnPinButton ? [clearOwnPinButton] : []),
        ]});

        return {action, row, checkbox};
    });
    const byCategory = new Map();
    entries.forEach((entry) => {
        if (!byCategory.has(entry.action.category)) byCategory.set(entry.action.category, []);
        byCategory.get(entry.action.category).push(entry.row);
    });
    const groups = [...byCategory.entries()].map(([category, rows]) => element('fieldset', {children: [
        element('legend', {text: category}),
        ...rows,
    ]}));
    const saveProtectedActions = element('button', {className: 'button', text: 'Auswahl speichern', attributes: {type: 'button'}});
    saveProtectedActions.addEventListener('click', async () => {
        const protectedActions = entries.filter((entry) => entry.checkbox.checked).map((entry) => entry.action.key);
        saveProtectedActions.disabled = true;
        try {
            await request('/api/admin/v1/pin-settings/protected-actions', {method: 'PUT', body: JSON.stringify({protectedActions})});
            toast('Der PIN-Schutz wurde aktualisiert.');
            await showPinProtection();
        } catch (error) {
            toast(error.message, 'error');
            saveProtectedActions.disabled = false;
        }
    });

    workspace.replaceChildren(
        sectionHeading('PIN-Schutzverwaltung', 'Global oder je Modul/Funktion einen eigenen PIN vergeben'),
        element('div', {className: 'card-list', children: [
            element('article', {className: 'management-card', children: [
                element('h3', {text: 'Globaler PIN'}),
                element('p', {className: 'field-hint', text: data.globalPinIsSet
                    ? 'Ein globaler PIN ist hinterlegt und gilt als Rückfall für jeden geschützten Eintrag ohne eigenen PIN. Beim Ändern wird der alte PIN sofort ungültig.'
                    : 'Es ist noch kein globaler PIN hinterlegt. Module/Funktionen ohne eigenen PIN lassen sich erst danach schützen.'}),
                pinField, setPin, message,
            ]}),
            element('article', {className: 'management-card', children: [
                element('h3', {text: 'Geschützte Module & Funktionen'}),
                element('p', {className: 'field-hint', text: 'Für ausgewählte Einträge wird der PIN zusätzlich zu den regulären Berechtigungen abgefragt — je Eintrag entweder der globale PIN oder ein eigener, davon abweichender.'}),
                ...(groups.length ? groups : [emptyState('Keine schützbaren Module/Funktionen bekannt.')]),
                saveProtectedActions,
            ]}),
        ]}),
    );
};

// Reiter „E-Mail-Einstellungen“ innerhalb von „E-Mail-Einstellungen“ (siehe
// `showEmailSettingsManagement`) — hier werden, je `NotificationEvent`, die zu benachrichtigenden
// Empfänger gepflegt (`AdminEmailSettingsController`). Erstes Beispiel eines Ereignisses: ein neuer
// Mitgliedsantrag (siehe `SubmitMembershipApplicationUseCase`). Die SMTP-Zugangsdaten selbst sind
// nicht hier admin-editierbar, sondern kommen aus der Deployment-Konfiguration (`MAILER_DSN`, siehe
// `NotificationMailer`). Die Texte der versendeten Mails selbst stehen ebenfalls nicht hier, sondern
// im Nachbar-Reiter „Mailvorlagen“ (`showMailTemplates`).
const showEmailConnectionSettings = async () => {
    const data = await request('/api/admin/v1/email-settings');

    const notificationRows = data.notificationEvents.map((event) => {
        const currentRecipients = (data.notificationRecipients[event.key] || []).join(', ');
        const recipientsField = field(event.label, `email-recipients-${event.key}`, currentRecipients);
        const saveRecipients = element('button', {className: 'secondary-button', text: 'Speichern', attributes: {type: 'button'}});
        saveRecipients.addEventListener('click', async () => {
            const recipients = recipientsField.querySelector('input').value.split(',').map((value) => value.trim()).filter(Boolean);
            saveRecipients.disabled = true;
            try {
                await request(`/api/admin/v1/email-settings/notifications/${event.key}`, {method: 'PUT', body: JSON.stringify({recipients})});
                toast('Die Empfänger wurden gespeichert.');
            } catch (error) {
                toast(error.message, 'error');
            } finally {
                saveRecipients.disabled = false;
            }
        });

        return element('div', {className: 'protected-action-row', children: [recipientsField, saveRecipients]});
    });

    workspace.replaceChildren(
        sectionHeading('E-Mail-Einstellungen', 'Empfänger für automatische Benachrichtigungen festlegen'),
        element('div', {className: 'card-list', children: [
            element('article', {className: 'management-card', children: [
                element('h3', {text: 'Benachrichtigungen'}),
                element('p', {className: 'field-hint', text: 'Mehrere Adressen mit Komma trennen. Leer lassen, um für dieses Ereignis keine Benachrichtigung zu versenden.'}),
                ...notificationRows,
            ]}),
        ]}),
    );
};

// Zeigt Betreff/HTML/Text einer mit Beispieldaten gerenderten Mailvorlage (siehe
// `PreviewMailTemplateUseCase`) in einem eigenen Dialog. Die HTML-Ansicht läuft in einem
// sandboxed <iframe> mit eigenem <html>/<head> (siehe `BrandedEmailLayout`) statt im DOM der
// Seite selbst, da die Mail ihre eigenen, teils widersprüchlichen Inline-Styles mitbringt.
const openMailTemplatePreview = (label, preview) => {
    const dialog = element('dialog', {className: 'preview-dialog'});
    const htmlFrame = element('iframe', {
        className: 'mail-preview-frame',
        attributes: {title: `Vorschau: ${preview.subject}`, sandbox: '', srcdoc: preview.html},
    });
    const textFrame = element('pre', {className: 'mail-preview-text', text: preview.text});
    textFrame.hidden = true;

    const htmlToggle = element('button', {className: 'editor-tool active', text: 'HTML-Ansicht', attributes: {type: 'button'}});
    const textToggle = element('button', {className: 'editor-tool', text: 'Text-Ansicht', attributes: {type: 'button'}});
    const setView = (view) => {
        htmlFrame.hidden = view !== 'html';
        textFrame.hidden = view !== 'text';
        htmlToggle.classList.toggle('active', view === 'html');
        textToggle.classList.toggle('active', view === 'text');
    };
    htmlToggle.addEventListener('click', () => setView('html'));
    textToggle.addEventListener('click', () => setView('text'));

    const close = element('button', {className: 'secondary-button', text: 'Vorschau schließen', attributes: {type: 'button'}});
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(
        element('header', {className: 'preview-toolbar', children: [
            element('strong', {text: `Vorschau: ${label}`}),
            element('div', {className: 'preview-actions', children: [htmlToggle, textToggle, close]}),
        ]}),
        element('div', {className: 'preview-stage', children: [htmlFrame, textFrame]}),
    );
    document.body.append(dialog);
    dialog.showModal();
};

// Reiter „Mailvorlagen“ innerhalb von „E-Mail-Einstellungen“ (siehe
// `showEmailSettingsManagement`) — die redaktionell pflegbaren Texte hinter jeder automatisch
// versendeten E-Mail (`MailTemplateKey`), statt sie im Code zu pflegen. Erstes Beispiel: die
// Bestätigungsmail bei einem angenommenen Mitgliedsantrag (`ReleaseMembershipApplicationUseCase`).
const showMailTemplates = async () => {
    const [data, signatureData] = await Promise.all([
        request('/api/admin/v1/mail-templates'),
        request('/api/admin/v1/mail-signatures'),
    ]);
    const signatures = signatureData.items;

    const cards = data.items.map((template) => {
        const message = formMessage();
        const subject = field('Betreff', `mail-template-subject-${template.key}`, template.subject);
        const body = field('Text', `mail-template-body-${template.key}`, template.body, 'textarea');
        const bodyInput = body.querySelector('textarea');
        bodyInput.rows = 10;

        // Die Signatur wird nur referenziert (nicht als Text kopiert, siehe
        // `MailTemplate::$signatureId`) — eine spätere Änderung der Signatur wirkt sich so
        // automatisch auf jede Vorlage aus, die sie zugeordnet hat, ohne diese einzeln
        // anzupassen. Beim Versand/in der Vorschau wird sie an den Text angehängt (siehe
        // `MailTemplateRenderer`).
        const signatureSelect = selectField('Signatur', `mail-template-signature-${template.key}`, [
            ['', 'Keine'],
            ...signatures.map((signature) => [signature.id, signature.name]),
        ], template.signatureId || '');
        const signatureSelectInput = signatureSelect.querySelector('select');

        const preview = element('button', {className: 'secondary-button', text: 'Vorschau', attributes: {type: 'button'}});
        preview.addEventListener('click', async () => {
            preview.disabled = true;
            try {
                const result = await request(`/api/admin/v1/mail-templates/${template.key}/preview`, {method: 'POST', body: JSON.stringify({
                    subject: subject.querySelector('input').value,
                    body: bodyInput.value,
                    signatureId: signatureSelectInput.value || null,
                })});
                openMailTemplatePreview(template.label, result);
            } catch (error) {
                toast(error.message, 'error');
            } finally {
                preview.disabled = false;
            }
        });

        const save = element('button', {className: 'button', text: 'Speichern', attributes: {type: 'button'}});
        save.addEventListener('click', async () => {
            save.disabled = true;
            try {
                await request(`/api/admin/v1/mail-templates/${template.key}`, {method: 'PUT', body: JSON.stringify({
                    subject: subject.querySelector('input').value,
                    body: bodyInput.value,
                    signatureId: signatureSelectInput.value || null,
                })});
                toast('Die Mailvorlage wurde gespeichert.');
                await showSettingsManagement();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
                save.disabled = false;
            }
        });
        const reset = element('button', {className: 'secondary-button', text: 'Auf Standard zurücksetzen', attributes: {type: 'button'}});
        reset.addEventListener('click', async () => {
            const confirmed = await confirmAction(
                'Auf Standard zurücksetzen',
                `„${template.label}“ wird auf den mitgelieferten Standardtext zurückgesetzt (auch die Signatur-Zuordnung). Eigene Anpassungen gehen dabei verloren.`,
                'Zurücksetzen',
            );
            if (!confirmed) return;
            try {
                await request(`/api/admin/v1/mail-templates/${template.key}/reset`, {method: 'POST'});
                toast('Die Mailvorlage wurde zurückgesetzt.');
                await showSettingsManagement();
            } catch (error) {
                toast(error.message, 'error');
            }
        });

        return element('article', {className: 'management-card', children: [
            element('h3', {text: template.label}),
            element('p', {className: 'field-hint', text: template.description}),
            element('p', {className: 'field-hint', text: `Verfügbare Platzhalter: ${template.placeholders.map((name) => `{{${name}}}`).join(', ')}`}),
            subject,
            body,
            signatureSelect,
            element('p', {className: 'field-hint', text: 'Die Signatur wird beim Versand automatisch an den Text angehängt — eine spätere Änderung an ihr wirkt sich auf jede Vorlage aus, die sie zugeordnet hat, ohne diese einzeln anzupassen. Verwaltung unter „Signaturen“.'}),
            element('p', {className: 'field-hint', text: 'Die Mail wird zusätzlich als gestaltete HTML-Ansicht im Design des Vereins verschickt — Leerzeilen im Text werden dabei zu Absätzen.'}),
            element('div', {className: 'confirm-dialog-actions', children: template.isDefault ? [preview, save] : [preview, save, reset]}),
            message,
        ]});
    });

    workspace.replaceChildren(
        sectionHeading('Mailvorlagen', 'Betreff und Text der automatisch versendeten E-Mails bearbeiten, statt sie im Code zu pflegen'),
        element('div', {className: 'card-list', children: cards}),
    );
};

// Reiter „Signaturen“ innerhalb von „E-Mail-Einstellungen“ (siehe `showEmailSettingsManagement`)
// — wiederverwendbare Signaturen (z. B. „Freundliche Grüße / Das Waldbad-Team / …“), die einer
// Mailvorlage (`showMailTemplates`) zugeordnet statt in deren Text kopiert werden: eine
// Änderung hier wirkt sich automatisch auf jede zugeordnete Vorlage aus (siehe
// `MailTemplate::$signatureId`, `MailTemplateRenderer`). Kann wie der übrige Vorlagentext
// `{{name}}`-Platzhalter enthalten — die werden erst beim Versand der jeweiligen Vorlage ersetzt.
const showMailSignatures = async () => {
    const data = await request('/api/admin/v1/mail-signatures');

    const signatureCard = (signature) => {
        const message = formMessage();
        const name = field('Name', `mail-signature-name-${signature?.id || 'new'}`, signature?.name || '');
        const body = field('Text', `mail-signature-body-${signature?.id || 'new'}`, signature?.body || '', 'textarea');
        body.querySelector('textarea').rows = 6;

        const save = element('button', {className: 'button', text: signature ? 'Speichern' : 'Anlegen', attributes: {type: 'button'}});
        save.addEventListener('click', async () => {
            save.disabled = true;
            try {
                const payload = JSON.stringify({
                    name: name.querySelector('input').value,
                    body: body.querySelector('textarea').value,
                });
                if (signature) {
                    await request(`/api/admin/v1/mail-signatures/${signature.id}`, {method: 'PUT', body: payload});
                    toast('Die Signatur wurde gespeichert.');
                } else {
                    await request('/api/admin/v1/mail-signatures', {method: 'POST', body: payload});
                    toast('Die Signatur wurde angelegt.');
                }
                await showSettingsManagement();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
                save.disabled = false;
            }
        });

        const actions = [save];
        if (signature) {
            const remove = element('button', {className: 'button danger-button', text: 'Löschen', attributes: {type: 'button'}});
            remove.addEventListener('click', async () => {
                const confirmed = await confirmAction(
                    'Signatur löschen',
                    `„${signature.name}“ wird gelöscht. Bereits in Mailvorlagen eingefügte Signaturtexte bleiben davon unberührt.`,
                );
                if (!confirmed) return;
                try {
                    await request(`/api/admin/v1/mail-signatures/${signature.id}`, {method: 'DELETE'});
                    toast('Die Signatur wurde gelöscht.');
                    await showSettingsManagement();
                } catch (error) {
                    toast(error.message, 'error');
                }
            });
            actions.unshift(remove);
        }

        return element('article', {className: 'management-card', children: [
            element('h3', {text: signature ? signature.name : 'Neue Signatur'}),
            name,
            body,
            element('div', {className: 'confirm-dialog-actions', children: actions}),
            message,
        ]});
    };

    workspace.replaceChildren(
        sectionHeading('Signaturen', 'Wiederverwendbare Signaturen, die Mailvorlagen zugeordnet werden können, statt sie in jede Vorlage zu kopieren'),
        element('p', {className: 'field-hint', text: 'Kann Platzhalter wie {{vereinsname}} enthalten. Welche Mailvorlage welche Signatur verwendet, wird im Reiter „Mailvorlagen“ je Vorlage ausgewählt — eine Änderung hier wirkt sich automatisch auf jede zugeordnete Vorlage aus.'}),
        element('div', {className: 'card-list', children: [...data.items.map(signatureCard), signatureCard(null)]}),
    );
};

// Modul „E-Mail-Einstellungen“ (Reiter innerhalb von „Einstellungen“, siehe
// `showSettingsManagement`): bündelt Mailserver-Zugangsdaten/Benachrichtigungsempfänger
// (`showEmailConnectionSettings`) und die Mailvorlagen-Texte (`showMailTemplates`) in eigenen
// Unter-Reitern, analog zu `showSettingsManagement` selbst.
const showEmailSettingsManagement = async () => {
    const tabs = [
        ['connection', 'E-Mail-Einstellungen', showEmailConnectionSettings],
        ['templates', 'Mailvorlagen', showMailTemplates],
        ['signatures', 'Signaturen', showMailSignatures],
    ];
    if (!tabs.some(([key]) => key === activeEmailSettingsTab)) activeEmailSettingsTab = tabs[0][0];
    setAdminPath(['einstellungen', 'e-mail', emailSlugsByTab[activeEmailSettingsTab]], true);

    const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'E-Mail-Einstellungen'}, children: tabs.map(([key, label]) => {
        const button = element('a', {
            className: `sub-tab${key === activeEmailSettingsTab ? ' active' : ''}`,
            text: label,
            attributes: {href: adminPath('einstellungen', 'e-mail', emailSlugsByTab[key])},
        });
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            activeEmailSettingsTab = key;
            setAdminPath(['einstellungen', 'e-mail', emailSlugsByTab[key]]);
            await showSettingsManagement();
        });

        return button;
    })});
    const active = tabs.find(([key]) => key === activeEmailSettingsTab);
    // showEmailConnectionSettings/showMailTemplates schreiben wie jedes andere Modul direkt in
    // `workspace`. Deren Ergebnis wird danach in ein Tab-Panel umgehängt, damit Tab-Leiste und
    // Inhalt gemeinsam sichtbar bleiben.
    await active[2]();
    const panel = element('div', {className: 'sub-tab-panel', children: [...workspace.children]});
    workspace.replaceChildren(tabStrip, panel);
};
const showUsers = async () => {
    const [data, pageOptions] = await Promise.all([
        request('/api/admin/v1/users'),
        request('/api/admin/v1/users/page-options'),
    ]);
    workspace.replaceChildren(
        sectionHeading('Benutzerverwaltung', 'Zugänge und Rollen verwalten'),
        ...(canEditModule('user_management') ? [userCreationForm(showUsers, pageOptions.items)] : []),
        element('div', {className: 'card-list', children: data.items.map((user) => userCard(user, showUsers, pageOptions.items))}),
    );
};

const MODULE_ROLE_LABELS = {
    viewer: 'Viewer',
    editor: 'Editor',
    publisher: 'Publisher',
    moderator: 'Moderator',
};

const globalRoleOptions = () => [
    ['', 'Keine globale Administratorrolle'],
    ...(hasAnyRole('admin', 'super_admin') ? [['admin', 'Admin']] : []),
    ...(hasAnyRole('super_admin') ? [['super_admin', 'Super-Admin']] : []),
];

const globalRoleField = (selectedRole = '') => {
    const select = element('select', {
        attributes: {name: 'globalRole'},
        children: globalRoleOptions().map(([value, label]) => element('option', {text: label, attributes: {value}})),
    });
    select.value = selectedRole;

    return element('label', {children: [element('span', {text: 'Globale Rolle'}), select]});
};

const moduleAccessFields = (selectedAccess = {}) => element('div', {
    className: 'module-access-list',
    children: CMS_MODULES.map(([module, label]) => {
        const roles = module === 'pages'
            ? ['viewer', 'editor', 'publisher', 'moderator']
            : ['viewer', 'editor'];
        const currentRole = selectedAccess[module] || 'viewer';
        const enabled = typeof selectedAccess[module] === 'string';
        const checkbox = element('input', {attributes: {type: 'checkbox', ...(enabled ? {checked: 'checked'} : {})}});
        const select = element('select', {
            attributes: {'aria-label': `Rolle für ${label}`, ...(enabled ? {} : {disabled: 'disabled'})},
            children: roles.map((role) => element('option', {text: MODULE_ROLE_LABELS[role], attributes: {value: role}})),
        });
        select.value = roles.includes(currentRole) ? currentRole : 'viewer';
        checkbox.addEventListener('change', () => select.disabled = !checkbox.checked);

        return element('div', {
            className: 'module-access-row',
            attributes: {'data-module': module},
            children: [
                element('label', {className: 'check-field', children: [checkbox, element('span', {text: label})]}),
                select,
            ],
        });
    }),
});

const readModuleAccess = (form) => Object.fromEntries(
    [...form.querySelectorAll('[data-module]')]
        .filter((row) => row.querySelector('input[type="checkbox"]').checked)
        .map((row) => [row.dataset.module, row.querySelector('select').value]),
);

const pageAccessFields = (selectedAccess = null, pages = []) => {
    const restricted = element('input', {attributes: {type: 'checkbox', ...(selectedAccess !== null ? {checked: 'checked'} : {})}});
    const list = element('div', {className: 'page-access-list'});
    const flattenedPages = flattenPageTree(buildPageTree(pages));
    flattenedPages.forEach(({page, depth}) => {
        const selectedRole = selectedAccess?.[page.id] || 'editor';
        const enabled = typeof selectedAccess?.[page.id] === 'string';
        const checkbox = element('input', {attributes: {type: 'checkbox', ...(enabled ? {checked: 'checked'} : {})}});
        const role = element('select', {
            attributes: {'aria-label': `Seitenrecht für ${page.title}`, ...(enabled ? {} : {disabled: 'disabled'})},
            children: [
                element('option', {text: 'Editor', attributes: {value: 'editor'}}),
                element('option', {text: 'Publisher', attributes: {value: 'publisher'}}),
            ],
        });
        role.value = selectedRole;
        checkbox.addEventListener('change', () => role.disabled = !checkbox.checked);
        list.append(element('div', {
            className: 'page-access-row',
            attributes: {'data-page-access-id': page.id},
            children: [
                element('label', {className: 'check-field', children: [
                    checkbox,
                    element('span', {text: `${'— '.repeat(depth)}${page.title}`}),
                ]}),
                role,
            ],
        }));
    });
    if (!flattenedPages.length) list.append(emptyState('Noch keine Seiten vorhanden.'));
    list.hidden = !restricted.checked;
    restricted.addEventListener('change', () => list.hidden = !restricted.checked);

    return element('div', {className: 'page-access-scope', children: [
        element('label', {className: 'check-field', children: [restricted, element('span', {text: 'Zugriff auf bestimmte Seiten beschränken'})]}),
        element('small', {className: 'field-hint', text: 'Dann werden im Modul Seiten ausschließlich die ausgewählten Seiten angezeigt. Admin und Super-Admin behalten Vollzugriff.'}),
        list,
    ]});
};

const readPageAccess = (form) => {
    const scope = form.querySelector('.page-access-scope');
    const restricted = scope.querySelector(':scope > .check-field input').checked;
    const pageAccess = Object.fromEntries(
        [...scope.querySelectorAll('[data-page-access-id]')]
            .filter((row) => row.querySelector('input[type="checkbox"]').checked)
            .map((row) => [row.dataset.pageAccessId, row.querySelector('select').value]),
    );

    return {restricted, pageAccess};
};

const canEditUser = (user) => {
    if (!canEditModule('user_management')) return false;
    if (user.roles.includes('super_admin')) return hasAnyRole('super_admin');
    if (user.roles.includes('admin')) return hasAnyRole('admin', 'super_admin');

    return true;
};

const openUserAccessDialog = (user, refresh, pages) => {
    const dialog = element('dialog', {className: 'activity-dialog'});
    const message = formMessage();
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    const submit = element('button', {className: 'button', text: 'Zugriffsrechte speichern', attributes: {type: 'submit'}});
    const form = element('form', {className: 'activity-dialog-content', children: [
        element('header', {children: [
            element('p', {className: 'eyebrow', text: 'Rechtemanagement'}),
            element('h2', {text: user.displayName}),
            element('p', {text: user.email}),
        ]}),
        element('fieldset', {children: [
            element('legend', {text: 'Globale Administratorrolle'}),
            globalRoleField(user.roles[0] || ''),
            element('small', {className: 'field-hint', text: 'Admin und Super-Admin erhalten innerhalb der freigeschalteten Module alle Rechte.'}),
        ]}),
        element('fieldset', {children: [
            element('legend', {text: 'Module und Rollen'}),
            moduleAccessFields(user.moduleAccess || {}),
        ]}),
        element('fieldset', {children: [
            element('legend', {text: 'Seitenbezogene Rechte'}),
            pageAccessFields(user.pageAccess, pages),
        ]}),
        message,
        element('div', {className: 'confirm-dialog-actions', children: [cancel, submit]}),
    ]});
    cancel.addEventListener('click', () => dialog.close());
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        const data = new FormData(form);
        const globalRole = data.get('globalRole');
        const pageAccess = readPageAccess(form);
        try {
            await request(`/api/admin/v1/users/${user.id}/access`, {method: 'PUT', body: JSON.stringify({
                roles: globalRole ? [globalRole] : [],
                moduleAccess: readModuleAccess(form),
                pageAccessRestricted: pageAccess.restricted,
                pageAccess: pageAccess.pageAccess,
            })});
            toast('Globale Rolle und Modulrechte wurden gespeichert.');
            dialog.close();
            await refresh();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
            submit.disabled = false;
        }
    });
    dialog.addEventListener('close', () => dialog.remove());
    dialog.append(form);
    document.body.append(dialog);
    dialog.showModal();
};

const userCard = (user, refresh, pages) => {
    const roleLabel = user.roles[0] === 'super_admin' ? 'Super-Admin' : (user.roles[0] === 'admin' ? 'Admin' : 'Keine');
    const moduleLabels = Object.entries(user.moduleAccess || {}).map(([module, role]) => {
        const label = CMS_MODULES.find(([value]) => value === module)?.[1] || module;
        return `${label}: ${MODULE_ROLE_LABELS[role] || role}`;
    });
    const children = [
        element('header', {children: [element('strong', {text: user.displayName}), element('small', {text: user.active ? 'aktiv' : 'gesperrt'})]}),
        element('a', {text: user.email, attributes: {href: 'mailto:' + user.email}}),
        element('p', {className: 'tag-line', text: `Globale Rolle: ${roleLabel}`}),
        element('p', {className: 'tag-line', text: `Module: ${moduleLabels.join(' · ')}`}),
        ...(user.pageAccess !== null ? [element('p', {className: 'tag-line', text: `Seitenscope: ${Object.keys(user.pageAccess).length} ausgewählte Seiten`})] : []),
    ];
    if (canEditUser(user)) {
        const actions = [];
        const edit = element('button', {className: 'secondary-button', text: 'Zugriff bearbeiten', attributes: {type: 'button'}});
        edit.addEventListener('click', () => openUserAccessDialog(user, refresh, pages));
        actions.push(edit);
        if (user.active) {
            const suspend = element('button', {className: 'text-button danger', text: 'Benutzer sperren', attributes: {type: 'button'}});
            suspend.addEventListener('click', async () => {
                const confirmed = await confirmAction(
                    `„${user.displayName}“ sperren?`,
                    'Der Benutzer kann sich danach nicht mehr im Backend anmelden.',
                    'Benutzer sperren',
                );
                if (!confirmed) return;
                suspend.disabled = true;
                try {
                    await request(`/api/admin/v1/users/${user.id}/suspend`, {method: 'POST'});
                    toast('Benutzer wurde gesperrt.');
                    await refresh();
                } catch (error) {
                    toast(error.message, 'error');
                    suspend.disabled = false;
                }
            });
            actions.push(suspend);
        }
        children.push(element('div', {className: 'card-actions', children: actions}));
    }

    return element('article', {className: 'management-card', children});
};

const userCreationForm = (refresh, pages) => {
    const message = formMessage();
    const form = element('form', {className: 'compact-form', children: [
        element('h3', {text: 'Benutzer anlegen'}),
        element('div', {className: 'form-grid', children: [field('Name', 'displayName'), field('E-Mail', 'email', '', 'email')]}),
        element('p', {className: 'field-hint', text: 'Der Zugang erfolgt ohne Passwort: die Person erhält beim Anmelden per Mail einen Anmeldelink.'}),
        element('fieldset', {children: [
            element('legend', {text: 'Globale Administratorrolle'}),
            globalRoleField(),
            element('small', {className: 'field-hint', text: 'Optional. Die Modulfreigaben bleiben auch für Administratoren verbindlich.'}),
        ]}),
        element('fieldset', {children: [element('legend', {text: 'Module und Rollen'}), moduleAccessFields()]}),
        element('fieldset', {children: [element('legend', {text: 'Seitenbezogene Rechte'}), pageAccessFields(null, pages)]}),
        message,
        element('button', {className: 'button', text: 'Zugang anlegen', attributes: {type: 'submit'}}),
    ]});
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        const globalRole = data.get('globalRole');
        const pageAccess = readPageAccess(form);
        try {
            await request('/api/admin/v1/users', {method: 'POST', body: JSON.stringify({
                displayName: data.get('displayName'), email: data.get('email'),
                roles: globalRole ? [globalRole] : [], moduleAccess: readModuleAccess(form),
                pageAccessRestricted: pageAccess.restricted, pageAccess: pageAccess.pageAccess,
            })});
            toast('Benutzerzugang wurde angelegt.');
            await refresh();
        } catch (error) {
            message.textContent = error.message;
            toast(error.message, 'error');
        }
    });
    return form;
};

const settingsTabsBySlug = {benutzer: 'users', 'pin-schutz': 'pin', 'e-mail': 'email'};
const settingsSlugsByTab = Object.fromEntries(Object.entries(settingsTabsBySlug).map(([slug, tab]) => [tab, slug]));
const emailTabsBySlug = {verbindung: 'connection', vorlagen: 'templates', signaturen: 'signatures'};
const emailSlugsByTab = Object.fromEntries(Object.entries(emailTabsBySlug).map(([slug, tab]) => [tab, slug]));
let activeSettingsTab = null;
let activeEmailSettingsTab = null;

// Modul „Einstellungen“: bündelt Bereiche, die entweder besondere Rechte (Benutzerverwaltung)
// oder gleich die Admin-/Super-Admin-Rolle (PIN-Schutzverwaltung, E-Mail-Einstellungen)
// voraussetzen — eigene Reiter-Leiste je nach freigeschaltetem Zugang.
const showSettingsManagement = async (segments = []) => {
    if (segments[0] === 'einstellungen' && settingsTabsBySlug[segments[1]]) {
        activeSettingsTab = settingsTabsBySlug[segments[1]];
    }
    if (segments[0] === 'einstellungen' && segments[1] === 'e-mail' && emailTabsBySlug[segments[2]]) {
        activeEmailSettingsTab = emailTabsBySlug[segments[2]];
    }
    const tabs = [
        ...(hasModule('user_management') ? [['users', 'Benutzerverwaltung', showUsers]] : []),
        // Bewusst über die Rolle statt über das reguläre Modul-Rechtesystem freigeschaltet:
        // PIN-Schutz und E-Mail-Einstellungen sind genau dafür da, auch vor Admins/Editoren mit
        // weitreichenden Modulrechten zusätzlich zu schützen bzw. sensible Zugangsdaten zu
        // verbergen — sie selbst über eine delegierbare Modulberechtigung zu steuern, würde
        // das aushebeln.
        ...(isGlobalAdministrator() ? [['pin', 'PIN-Schutzverwaltung', showPinProtection]] : []),
        ...(isGlobalAdministrator() ? [['email', 'E-Mail-Einstellungen', showEmailSettingsManagement]] : []),
    ];
    if (!tabs.some(([key]) => key === activeSettingsTab)) activeSettingsTab = tabs[0]?.[0] || null;
    if (activeSettingsTab && activeSettingsTab !== 'email') {
        setAdminPath(['einstellungen', settingsSlugsByTab[activeSettingsTab]], true);
    }

    const tabStrip = element('nav', {className: 'sub-tab-strip', attributes: {'aria-label': 'Einstellungen'}, children: tabs.map(([key, label]) => {
        const settingsPath = key === 'email'
            ? adminPath('einstellungen', 'e-mail', emailSlugsByTab[activeEmailSettingsTab || 'connection'])
            : adminPath('einstellungen', settingsSlugsByTab[key]);
        const button = element('a', {
            className: `sub-tab${key === activeSettingsTab ? ' active' : ''}`,
            text: label,
            attributes: {href: settingsPath},
        });
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            activeSettingsTab = key;
            if (key === 'email') {
                activeEmailSettingsTab = activeEmailSettingsTab || 'connection';
                setAdminPath(['einstellungen', 'e-mail', emailSlugsByTab[activeEmailSettingsTab]]);
            } else {
                setAdminPath(['einstellungen', settingsSlugsByTab[key]]);
            }
            await showSettingsManagement();
        });

        return button;
    })});
    const active = tabs.find(([key]) => key === activeSettingsTab);
    if (!active) {
        workspace.replaceChildren(tabStrip, emptyState('Für diesen Zugang ist kein Bereich der Einstellungen freigeschaltet.'));
        return;
    }
    // showUsers/showPinProtection/showEmailSettingsManagement schreiben wie jedes andere Modul direkt in `workspace`. Deren
    // Ergebnis wird danach in ein Tab-Panel umgehängt, damit Tab-Leiste und Inhalt gemeinsam
    // sichtbar bleiben.
    await active[2]();
    const panel = element('div', {className: 'sub-tab-panel', children: [...workspace.children]});
    workspace.replaceChildren(tabStrip, panel);
};

export {showSettingsManagement};
