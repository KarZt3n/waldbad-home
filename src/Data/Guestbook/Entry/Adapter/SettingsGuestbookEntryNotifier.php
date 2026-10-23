<?php

namespace App\Data\Guestbook\Entry\Adapter;

use App\Logic\Guestbook\Entry\GuestbookEntryAdminLinkBuilderInterface;
use App\Logic\Guestbook\Entry\GuestbookEntryNotifierInterface;
use App\Logic\Guestbook\Entry\Model\GuestbookEntry;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Bindet den Mailversand der Einstellungen an das Gästebuch an (Adapter-Prinzip,
 * `architektur.md` Abschnitt 2.4): Empfänger kommen aus „Einstellungen“ → „E-Mail-Einstellungen“
 * → „Benachrichtigungen“, der Text aus der Mailvorlage. Ohne hinterlegte Empfänger wird nichts
 * versendet (siehe `NotificationMailer::notify()`).
 */
readonly class SettingsGuestbookEntryNotifier implements GuestbookEntryNotifierInterface
{
    public function __construct(
        private NotificationMailer $mailer,
        private GuestbookEntryAdminLinkBuilderInterface $adminLinks,
    ) {
    }

    public function entrySubmitted(GuestbookEntry $entry): void
    {
        $this->mailer->notify(NotificationEvent::GuestbookEntrySubmitted, MailTemplateKey::GuestbookEntrySubmittedNotification, [
            'name' => $entry->displayName,
            'email' => $entry->email ?? '–',
            'nachricht' => $entry->message,
            'link' => $this->adminLinks->build($entry->id),
        ]);
    }
}
