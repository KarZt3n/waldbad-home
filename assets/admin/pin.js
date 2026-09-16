// PIN-Schutz für einzelne Redaktions-Module/Aktionen (siehe „Einstellungen“ → PIN-Schutz,
// `ProtectedAction`) — sitzungsweites Entsperren (`ensurePinUnlocked`, `sessionStorage`) für ganze
// Module sowie eine je-Aktion-neue Abfrage (`promptForPin`) für einzelne, folgenschwere Aktionen
// wie „Mitglied löschen“ (siehe `admin/members.js`, `deleteMemberWithOptionalPin`).

import {element, field, formMessage, request} from '../core.js';

const promptForPin = (title, description, verify) => new Promise((resolve) => {
    const dialog = element('dialog', {className: 'confirm-dialog'});
    const pinField = field('PIN', 'pin-prompt-input', '', 'password');
    const pinInput = pinField.querySelector('input');
    pinInput.inputMode = 'numeric';
    pinInput.autocomplete = 'off';
    pinInput.maxLength = 8;
    const message = formMessage();
    const cancel = element('button', {className: 'secondary-button', text: 'Abbrechen', attributes: {type: 'button'}});
    const confirm = element('button', {className: 'button', text: 'Bestätigen', attributes: {type: 'submit'}});
    let answered = false;
    const finish = (result) => {
        answered = true;
        resolve(result);
        dialog.close();
    };
    const form = element('form', {className: 'confirm-dialog-content', children: [
        element('p', {className: 'eyebrow', text: 'PIN erforderlich'}),
        element('h2', {text: title}),
        element('p', {text: description}),
        pinField,
        message,
        element('div', {className: 'confirm-dialog-actions', children: [cancel, confirm]}),
    ]});
    cancel.addEventListener('click', () => finish(false));
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        finish(false);
    });
    dialog.addEventListener('close', () => {
        if (!answered) resolve(false);
        dialog.remove();
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const pin = pinInput.value.trim();
        if (!pin) return;
        confirm.disabled = true;
        try {
            await verify(pin);
            finish(true);
        } catch (error) {
            message.textContent = error.message;
            pinInput.value = '';
            pinInput.focus();
        } finally {
            confirm.disabled = false;
        }
    });
    dialog.append(form);
    document.body.append(dialog);
    dialog.showModal();
    pinInput.focus();
});

// „Entsperrt“ ein per PIN geschütztes Modul (siehe `ProtectedAction`) für den Rest der
// Browser-Sitzung, statt bei jedem erneuten Öffnen desselben Bereichs erneut nach dem PIN zu
// fragen — anders als bei einzelnen, folgenschweren Aktionen (z. B. „Mitglied löschen“, siehe
// `deleteMemberWithOptionalPin`), wo bewusst jedes Mal neu geprüft wird. sessionStorage-Zugriffe
// sind bewusst abgesichert: in manchen Browserkontexten (z. B. privates Fenster) kann der Zugriff
// werfen, das darf die eigentliche Freischaltung nicht verhindern.
const isPinSessionUnlocked = (actionKey) => {
    try { return sessionStorage.getItem(`pin-unlocked:${actionKey}`) === 'true'; } catch { return false; }
};
const markPinSessionUnlocked = (actionKey) => {
    try { sessionStorage.setItem(`pin-unlocked:${actionKey}`, 'true'); } catch { /* siehe oben */ }
};
// Beim Abmelden aufgerufen: Ein „Entsperrt“-Status darf nicht in eine andere Sitzung im selben
// Browser-Tab übernommen werden (z. B. wenn sich danach ein anderer Benutzer anmeldet).
const clearPinSessionUnlocks = () => {
    try {
        Object.keys(sessionStorage)
            .filter((key) => key.startsWith('pin-unlocked:'))
            .forEach((key) => sessionStorage.removeItem(key));
    } catch { /* siehe oben */ }
};

/**
 * Prüft, ob $actionKey aktuell per PIN geschützt ist, und fragt den PIN bei Bedarf ab. Liefert
 * `true`, wenn die aufrufende Stelle fortfahren darf (kein Schutz aktiv, bereits in dieser Sitzung
 * entsperrt, oder gerade erfolgreich per PIN entsperrt) — `false` bei „Abbrechen“.
 */
const ensurePinUnlocked = async (actionKey, title) => {
    if (isPinSessionUnlocked(actionKey)) return true;
    let settings;
    try {
        settings = await request('/api/admin/v1/pin-settings');
    } catch {
        // Einstellungen nicht abrufbar (z. B. Netzwerkfehler) — nicht blockieren, die eigentliche
        // Aktion greift ohnehin nur, wenn die reguläre Berechtigung dafür vorliegt.
        return true;
    }
    if (!settings.protectedActions.includes(actionKey)) return true;

    const unlocked = await promptForPin(title, 'Für diesen Bereich ist zusätzlich ein PIN erforderlich.', (pin) => request(
        '/api/admin/v1/pin-settings/verify',
        {method: 'POST', body: JSON.stringify({action: actionKey, pin})},
    ));
    if (unlocked) markPinSessionUnlocked(actionKey);

    return unlocked;
};

export {promptForPin, isPinSessionUnlocked, markPinSessionUnlocked, clearPinSessionUnlocks, ensurePinUnlocked};
