<?php

namespace App\Data\Event\HelpRequest\Adapter;

use App\Logic\Event\HelpRequest\EventHelpRequestAdminLinkBuilderInterface;
use App\Logic\Event\HelpRequest\EventHelpRequestNotifierInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestSubmission;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Bindet den Mailversand der Einstellungen an die Helferanmeldung an (Adapter-Prinzip,
 * `architektur.md` Abschnitt 2.4): Empfänger kommen aus „Einstellungen“ → „E-Mail-Einstellungen“
 * → „Benachrichtigungen“, der Text aus der Mailvorlage. Ohne hinterlegte Empfänger wird nichts
 * versendet (siehe `NotificationMailer::notify()`).
 */
readonly class SettingsEventHelpRequestNotifier implements EventHelpRequestNotifierInterface
{
    public function __construct(
        private NotificationMailer $mailer,
        private EventHelpRequestAdminLinkBuilderInterface $adminLinks,
    ) {
    }

    public function helpRequestSubmitted(EventHelpRequestSubmission $submission): void
    {
        $event = $submission->event;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $event->date);
        $personNames = $submission->personNames();
        $this->mailer->notify(NotificationEvent::EventHelpRequestSubmitted, MailTemplateKey::EventHelpRequestSubmittedNotification, [
            'vorname' => $submission->firstName,
            'nachname' => $submission->lastName,
            'veranstaltung' => $event->title,
            'datum' => $date !== false ? $date->format('d.m.Y') : $event->date,
            'uhrzeit' => $event->time !== '' ? $event->time : '–',
            'personen' => implode("\n", array_map(static fn (string $name): string => '- '.$name, $personNames)),
            'personenzahl' => (string) count($personNames),
            'aktivitaeten' => $submission->activityNames === [] ? 'keine ausgewählt' : implode(', ', $submission->activityNames),
            'email' => $submission->email ?? '–',
            'mitglied' => $submission->memberNumber ?? 'nicht zugeordnet',
            'nachricht' => trim($submission->message) === '' ? 'keine' : $submission->message,
            'link' => $this->adminLinks->build($event->identifier),
        ]);
    }
}
