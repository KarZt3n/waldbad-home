<?php

namespace App\Data\Contact\Request\Adapter;

use App\Logic\Contact\Request\ContactRequestAdminLinkBuilderInterface;
use App\Logic\Contact\Request\ContactRequestNotifierInterface;
use App\Logic\Contact\Request\Model\ContactRequest;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Bindet den Mailversand der Einstellungen an das Kontaktformular an (Adapter-Prinzip,
 * `architektur.md` Abschnitt 2.4): Empfänger kommen aus „Einstellungen“ → „E-Mail-Einstellungen“
 * → „Benachrichtigungen“, der Text aus der Mailvorlage. Ohne hinterlegte Empfänger wird nichts
 * versendet (siehe `NotificationMailer::notify()`).
 */
readonly class SettingsContactRequestNotifier implements ContactRequestNotifierInterface
{
    public function __construct(
        private NotificationMailer $mailer,
        private ContactRequestAdminLinkBuilderInterface $adminLinks,
    ) {
    }

    public function contactRequestSubmitted(ContactRequest $request): void
    {
        $this->mailer->notify(NotificationEvent::ContactRequestSubmitted, MailTemplateKey::ContactRequestSubmittedNotification, [
            'name' => $request->name,
            'email' => $request->email,
            'betreff' => $request->subject !== null && $request->subject !== '' ? $request->subject : '(ohne Betreff)',
            'nachricht' => $request->message,
            'link' => $this->adminLinks->build($request->id),
        ]);
    }
}
