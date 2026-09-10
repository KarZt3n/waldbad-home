<?php

namespace App\Logic\Settings\MailTemplate\Model;

readonly class MailTemplate
{
    public function __construct(
        public MailTemplateKey $key,
        public string $subject,
        public string $body,
        /**
         * Referenziert eine wiederverwendbare Signatur (siehe `MailSignature`), statt deren Text
         * hier zu duplizieren — wird erst beim Rendern (siehe `MailTemplateRenderer`) an den Text
         * angehängt. So muss bei einer Änderung der Signatur (z. B. neue Adresse) nicht jede
         * Vorlage einzeln angepasst werden. `null`, solange keine Signatur zugeordnet ist; bleibt
         * die referenzierte Signatur bestehen, auch wenn sie später gelöscht wird — die Zuordnung
         * läuft dann einfach ins Leere (siehe `MailTemplateRenderer::renderText()`).
         */
        public ?string $signatureId = null,
    ) {
    }

    public function isDefault(): bool
    {
        return $this->subject === $this->key->defaultSubject()
            && $this->body === $this->key->defaultBody()
            && $this->signatureId === null;
    }

    public function withText(string $subject, string $body, ?string $signatureId): self
    {
        return new self($this->key, $subject, $body, $signatureId);
    }
}
