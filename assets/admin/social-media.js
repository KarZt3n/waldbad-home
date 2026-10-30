// Unterreiter „Social Media“ unter „Seiten“: ob die Social-Media-Leiste am rechten Rand aller
// öffentlichen Seiten angezeigt wird und welche Plattformen sie in welcher Reihenfolge verlinkt.
// Änderungen sind ohne Freigabe sofort online (siehe `PageAuthorizationService::assertCanEditSocialMedia`).

import {element, formMessage, request, toast} from '../core.js';
import {sectionHeading} from './ui.js';
import {canEditSocialMedia} from './session.js';

const renderSocialMedia = async (mount) => {
    const data = await request('/api/admin/v1/social-media');
    const editable = canEditSocialMedia();
    const labels = new Map(data.platforms.map((platform) => [platform.key, platform.label]));
    let links = data.links.map((link) => ({platform: link.platform, url: link.url}));

    const visible = element('input', {attributes: {type: 'checkbox', id: 'social-media-visible'}});
    visible.checked = data.visible;
    visible.disabled = !editable;
    const list = element('div', {className: 'social-media-links'});
    const addSelect = element('select', {attributes: {'aria-label': 'Plattform hinzufügen'}});
    const addButton = element('button', {className: 'secondary-button', text: '＋ Hinzufügen', attributes: {type: 'button'}});
    const addRow = element('div', {className: 'social-media-add', children: [addSelect, addButton]});
    const message = formMessage();

    const renderLinks = () => {
        list.replaceChildren(...links.map((link, index) => {
            const url = element('input', {attributes: {
                type: 'url',
                id: `social-media-url-${link.platform}`,
                placeholder: 'https://…',
                maxlength: '500',
            }});
            url.value = link.url;
            url.disabled = !editable;
            url.addEventListener('input', () => link.url = url.value);
            const move = (offset, text, label) => {
                const button = element('button', {className: 'tree-icon-button', text, attributes: {type: 'button', 'aria-label': label}});
                button.disabled = !editable || !links[index + offset];
                button.addEventListener('click', () => {
                    [links[index], links[index + offset]] = [links[index + offset], links[index]];
                    renderLinks();
                });

                return button;
            };
            const remove = element('button', {className: 'tree-icon-button danger', text: '×', attributes: {type: 'button', 'aria-label': `${labels.get(link.platform)} entfernen`}});
            remove.disabled = !editable;
            remove.addEventListener('click', () => {
                links = links.filter((entry) => entry !== link);
                renderLinks();
            });

            return element('div', {className: 'social-media-link-row', children: [
                element('label', {className: 'field', children: [element('span', {text: labels.get(link.platform)}), url]}),
                element('div', {className: 'social-media-link-actions', children: [
                    move(-1, '↑', `${labels.get(link.platform)} nach oben`),
                    move(1, '↓', `${labels.get(link.platform)} nach unten`),
                    remove,
                ]}),
            ]});
        }));
        if (!links.length) list.append(element('p', {className: 'empty-copy', text: 'Noch keine Plattform eingetragen.'}));
        const used = new Set(links.map((link) => link.platform));
        const available = data.platforms.filter((platform) => !used.has(platform.key));
        addSelect.replaceChildren(...available.map((platform) => element('option', {text: platform.label, attributes: {value: platform.key}})));
        addRow.hidden = !editable || !available.length;
    };
    addButton.addEventListener('click', () => {
        const platform = addSelect.value;
        if (!platform) return;
        links.push({platform, url: ''});
        renderLinks();
        list.querySelector(`#social-media-url-${platform}`)?.focus();
    });
    renderLinks();

    const actions = [];
    if (editable) {
        const save = element('button', {className: 'button', text: 'Speichern', attributes: {type: 'button'}});
        save.addEventListener('click', async () => {
            save.disabled = true;
            message.textContent = '';
            try {
                await request('/api/admin/v1/social-media', {method: 'PUT', body: JSON.stringify({visible: visible.checked, links})});
                toast('Die Social-Media-Links wurden gespeichert und sind sofort online.');
                await mount();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
                save.disabled = false;
            }
        });
        actions.push(save);
    }

    return [
        sectionHeading('Social Media', 'Leiste mit Social-Media-Links am rechten Rand aller Seiten'),
        element('article', {className: 'management-card social-media-editor', children: [
            ...(editable ? [] : [element('p', {className: 'field-hint', text: 'Ändern dürfen nur Redakteure mit Veröffentlichungsrecht für alle Seiten, da die Links ohne Freigabe sofort online sind.'})]),
            element('label', {className: 'check-field', children: [visible, element('span', {text: 'Social-Media-Leiste auf der Website anzeigen'})]}),
            list,
            addRow,
            ...(actions.length ? [element('div', {className: 'confirm-dialog-actions', children: actions})] : []),
            message,
        ]}),
    ];
};

export {renderSocialMedia};
