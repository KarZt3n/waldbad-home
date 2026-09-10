<?php

namespace App\Logic\Settings\MailSignature\Manager;

use App\Logic\Settings\MailSignature\Model\MailSignature;

interface MailSignatureManagerInterface
{
    public function get(string $id): MailSignature;

    /**
     * Wie `get()`, aber `null` statt einer Exception, wenn keine Signatur mit dieser Id existiert
     * — für Stellen, an denen eine ungültig gewordene Referenz (z. B. eine später gelöschte, aber
     * noch einer Mailvorlage zugeordnete Signatur, siehe `MailTemplate::$signatureId`) kein Fehler
     * sein soll.
     */
    public function find(string $id): ?MailSignature;

    /**
     * @return list<MailSignature>
     */
    public function list(): array;

    public function save(MailSignature $signature): MailSignature;

    public function delete(string $id): void;
}
