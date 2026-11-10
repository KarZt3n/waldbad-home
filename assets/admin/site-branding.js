// Unterreiter „Logo“ unter „Seiten“: Logo in der Kopfzeile aller öffentlichen Seiten, aus der
// Mediathek gewählt oder hochgeladen. Ohne eigenes Logo gilt das mitgelieferte Standardlogo.
// Änderungen sind ohne Freigabe sofort online (siehe `PageAuthorizationService::assertCanEditBranding`).

import {element, field, formMessage, request, toast} from '../core.js';
import {sectionHeading} from './ui.js';
import {canEditSiteBranding} from './session.js';
import {openImagePicker} from './media-picker.js';

const renderSiteBranding = async (mount) => {
    const data = await request('/api/admin/v1/site-branding');
    const editable = canEditSiteBranding();
    const message = formMessage();
    const url = field('Logo-Datei (leer = Standardlogo)', 'site-branding-logo-url', data.customLogoUrl || '');
    const urlInput = url.querySelector('input');
    urlInput.placeholder = data.defaultLogoUrl;
    urlInput.maxLength = 500;
    urlInput.disabled = !editable;
    const previewImage = element('img', {attributes: {alt: 'Vorschau des Logos'}});
    const preview = element('div', {className: 'site-branding-preview', children: [
        previewImage,
        element('span', {children: [element('strong', {text: 'Waldbad Borkheide'}), element('small', {text: '… natürlich baden!'})]}),
    ]});
    const refreshPreview = () => previewImage.src = urlInput.value.trim() || data.defaultLogoUrl;
    urlInput.addEventListener('input', refreshPreview);
    refreshPreview();

    const setUrl = (value, text) => {
        urlInput.value = value;
        refreshPreview();
        message.textContent = '';
        if (text) toast(text);
    };
    const actions = [];
    if (editable) {
        // Auswahl und Upload laufen im Mediathek-Overlay (Ordnerbaum im Dialog, siehe `media-picker.js`).
        const select = element('button', {className: 'secondary-button', text: 'Bild auswählen …', attributes: {type: 'button'}});
        select.addEventListener('click', () => openImagePicker((image) => setUrl(image.url, `${image.originalName} wurde ausgewählt.`)));
        const reset = element('button', {className: 'text-button', text: 'Standardlogo verwenden', attributes: {type: 'button'}});
        reset.addEventListener('click', () => setUrl(''));
        const save = element('button', {className: 'button', text: 'Speichern', attributes: {type: 'button'}});
        save.addEventListener('click', async () => {
            save.disabled = true;
            message.textContent = '';
            try {
                await request('/api/admin/v1/site-branding', {method: 'PUT', body: JSON.stringify({logoUrl: urlInput.value.trim() || null})});
                toast('Das Logo wurde gespeichert und ist sofort online.');
                await mount();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
                save.disabled = false;
            }
        });
        actions.push(element('div', {className: 'site-branding-actions', children: [select, reset]}));
        actions.push(element('div', {className: 'confirm-dialog-actions', children: [save]}));
    }

    return [
        sectionHeading('Logo', 'Logo in der Kopfzeile aller Seiten'),
        element('article', {className: 'management-card site-branding-editor', children: [
            ...(editable ? [] : [element('p', {className: 'field-hint', text: 'Ändern dürfen nur Redakteure mit Veröffentlichungsrecht für alle Seiten, da das Logo ohne Freigabe sofort online ist.'})]),
            preview,
            url,
            element('p', {className: 'field-hint', text: 'Am besten eine SVG- oder PNG-Datei mit transparentem Hintergrund. Das Logo wird etwa 96 Pixel breit angezeigt.'}),
            ...actions,
            message,
        ]}),
    ];
};

export {renderSiteBranding};
