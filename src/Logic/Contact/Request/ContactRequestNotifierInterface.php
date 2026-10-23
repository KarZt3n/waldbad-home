<?php

namespace App\Logic\Contact\Request;

use App\Logic\Contact\Request\Model\ContactRequest;

/**
 * Adapter zum Mailversand der Einstellungen: meldet eine neue Kontaktanfrage an die dafür
 * konfigurierten Empfänger, ohne dass das Kontaktmodul Mailvorlagen oder Empfängerlisten kennt.
 */
interface ContactRequestNotifierInterface
{
    public function contactRequestSubmitted(ContactRequest $request): void;
}
