<?php

namespace App\Logic\Settings\MailSignature\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Eine wiederverwendbare Signatur (z. B. „Freundliche Grüße / Das Waldbad-Team / …“), die Admins
 * im Reiter „Signaturen“ (Modul „Einstellungen“ → „E-Mail-Einstellungen“) zentral pflegen und
 * anschließend über den Editor einer Mailvorlage (siehe `MailTemplateKey`) an gewünschter Stelle in
 * deren Text einfügen — der Text landet dabei als normaler Bestandteil der jeweiligen Vorlage in
 * deren gespeichertem Text, diese Klasse ist nur die zentrale, wiederverwendbare Quelle dafür.
 *
 * Kann wie der übrige Vorlagentext `{{name}}`-Platzhalter enthalten (z. B. `{{vereinsname}}`) —
 * die werden erst beim Versand der jeweiligen Mailvorlage ersetzt, in die die Signatur eingefügt
 * wurde; nur dort verfügbare Platzhalter werden tatsächlich ersetzt.
 */
readonly class MailSignature
{
    public function __construct(
        public string $id,
        public string $name,
        public string $body,
    ) {
        if (trim($this->name) === '') {
            throw new BusinessRuleViolationException('Der Name der Signatur ist erforderlich.');
        }
        if (trim($this->body) === '') {
            throw new BusinessRuleViolationException('Der Text der Signatur ist erforderlich.');
        }
    }

    public function withText(string $name, string $body): self
    {
        return new self($this->id, $name, $body);
    }
}
