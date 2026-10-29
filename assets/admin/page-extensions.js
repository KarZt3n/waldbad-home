// Unterreiter „Erweiterungen“ unter „Seiten“: alle Seitenerweiterungen (Blocktyp „Erweiterung“) mit
// den Seiten, auf denen sie eingebunden sind, und – sofern die Erweiterung Texte hat – deren Pflege.
// Gespeichert werden nur Abweichungen vom mitgelieferten Standardtext (siehe `PageExtensionTexts`).

import {confirmAction, element, field, formMessage, request, toast} from '../core.js';
import {emptyState, sectionHeading} from './ui.js';
import {canEditExtensionTexts} from './session.js';
import {adminPath, setAdminPath} from './shell.js';

const extensionUsage = (pages) => {
    const usage = new Map();
    pages.forEach((page) => (page.blocks || []).forEach((block) => {
        if (block.type !== 'extension' || !block.extensionKey) return;
        const list = usage.get(block.extensionKey) || [];
        if (!list.some((entry) => entry.id === page.id)) list.push(page);
        usage.set(block.extensionKey, list);
    }));

    return usage;
};

const pageLinks = (pages) => pages.length
    ? element('p', {className: 'field-hint', children: [
        element('span', {text: 'Eingebunden auf: '}),
        ...pages.flatMap((page, index) => [
            ...(index > 0 ? [element('span', {text: ', '})] : []),
            element('a', {text: page.title, attributes: {href: adminPath('seiten', page.id)}}),
        ]),
    ]})
    : element('p', {className: 'field-hint', text: 'Derzeit auf keiner Seite eingebunden.'});

const textEditor = (extension, onSaved) => {
    const editable = canEditExtensionTexts();
    const message = formMessage();
    const inputs = new Map();
    const fields = extension.fields.map((textField) => {
        const wrapper = field(textField.label, `extension-${extension.key}-${textField.key}`, textField.text, textField.multiline ? 'textarea' : 'text');
        const input = wrapper.querySelector('input, textarea');
        if (textField.multiline) input.rows = 3;
        input.disabled = !editable;
        inputs.set(textField.key, input);
        const hint = element('small', {className: 'field-hint page-extension-default'});
        const refreshHint = () => {
            const isDefault = input.value.trim() === textField.defaultText;
            hint.replaceChildren(element('span', {text: isDefault ? 'Standardtext' : `Standard: ${textField.defaultText}`}));
            if (!isDefault && editable) {
                const restore = element('button', {className: 'text-button', text: 'Standard übernehmen', attributes: {type: 'button'}});
                restore.addEventListener('click', () => {
                    input.value = textField.defaultText;
                    refreshHint();
                });
                hint.append(restore);
            }
        };
        input.addEventListener('input', refreshHint);
        refreshHint();
        wrapper.append(hint);
        if (textField.consent) {
            wrapper.querySelector('span').append(element('span', {className: 'status-badge status-pending page-extension-consent', text: 'Bestätigungstext'}));
        }

        return wrapper;
    });

    const actions = [];
    if (editable) {
        const save = element('button', {className: 'button', text: 'Speichern', attributes: {type: 'button'}});
        save.addEventListener('click', async () => {
            const changesConsent = extension.fields.some((textField) => textField.consent && inputs.get(textField.key).value.trim() !== textField.text);
            if (changesConsent && !(await confirmAction(
                'Bestätigungstext ändern?',
                'Neue Anträge speichern ab sofort den geänderten Wortlaut. Wer das Formular gerade geöffnet hat, muss die Seite vor dem Absenden neu laden.',
                'Speichern',
            ))) return;
            save.disabled = true;
            try {
                await request(`/api/admin/v1/page-extensions/${extension.key}`, {method: 'PUT', body: JSON.stringify({
                    texts: Object.fromEntries([...inputs].map(([key, input]) => [key, input.value])),
                })});
                toast('Die Texte wurden gespeichert und sind sofort online.');
                await onSaved();
            } catch (error) {
                message.textContent = error.message;
                toast(error.message, 'error');
                save.disabled = false;
            }
        });
        actions.push(save);
        if (extension.fields.some((textField) => !textField.isDefault)) {
            const reset = element('button', {className: 'secondary-button', text: 'Alle auf Standard zurücksetzen', attributes: {type: 'button'}});
            reset.addEventListener('click', async () => {
                if (!(await confirmAction(
                    'Auf Standard zurücksetzen',
                    `Alle Texte von „${extension.label}“ werden auf den mitgelieferten Standard zurückgesetzt. Eigene Anpassungen gehen dabei verloren.`,
                    'Zurücksetzen',
                ))) return;
                try {
                    await request(`/api/admin/v1/page-extensions/${extension.key}/reset`, {method: 'POST'});
                    toast('Die Texte wurden zurückgesetzt.');
                    await onSaved();
                } catch (error) {
                    toast(error.message, 'error');
                }
            });
            actions.push(reset);
        }
    }

    return element('div', {className: 'page-extension-editor', children: [
        ...(editable ? [] : [element('p', {className: 'field-hint', text: 'Ändern dürfen nur Redakteure mit Veröffentlichungsrecht für alle Seiten, da die Texte ohne Freigabe sofort online sind.'})]),
        ...fields,
        ...(actions.length ? [element('div', {className: 'confirm-dialog-actions', children: actions})] : []),
        message,
    ]});
};

const renderPageExtensions = async (selectedKey, mount) => {
    const [extensionData, pageData] = await Promise.all([
        request('/api/admin/v1/page-extensions'),
        request('/api/admin/v1/pages'),
    ]);
    const usage = extensionUsage(pageData.items);
    const reload = () => mount(selectedKey);

    const cards = extensionData.items.map((extension) => {
        const hasTexts = extension.fields.length > 0;
        const customized = extension.fields.filter((textField) => !textField.isDefault).length;
        const header = element('header', {children: [
            element('div', {children: [
                element('strong', {text: extension.label}),
                element('small', {text: extension.description}),
            ]}),
            ...(customized ? [element('span', {className: 'status-badge status-released', text: `${customized} ${customized === 1 ? 'Text' : 'Texte'} angepasst`})] : []),
        ]});
        const card = element('article', {className: 'management-card page-extension-card', attributes: {id: `extension-${extension.key}`}, children: [
            header,
            pageLinks(usage.get(extension.key) || []),
        ]});
        if (!hasTexts) {
            card.append(element('p', {className: 'field-hint', text: 'Diese Erweiterung hat keine anpassbaren Texte.'}));
            return card;
        }
        const details = element('details', {children: [element('summary', {text: canEditExtensionTexts() ? 'Texte anpassen' : 'Texte anzeigen'})]});
        details.open = extension.key === selectedKey;
        details.addEventListener('toggle', () => {
            if (details.open) {
                selectedKey = extension.key;
                setAdminPath(['seiten', 'erweiterungen', extension.key], true);
                if (details.children.length === 1) details.append(textEditor(extension, reload));
            } else if (selectedKey === extension.key) {
                selectedKey = null;
                setAdminPath(['seiten', 'erweiterungen'], true);
            }
        });
        if (details.open) details.append(textEditor(extension, reload));
        card.append(details);

        return card;
    });

    return [
        sectionHeading('Erweiterungen', 'Seitenerweiterungen im Überblick und ihre Texte anpassen'),
        cards.length ? element('div', {className: 'card-list', children: cards}) : emptyState('Keine Erweiterungen vorhanden.'),
    ];
};

export {renderPageExtensions};
