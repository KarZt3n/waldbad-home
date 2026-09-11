<?php

namespace App\Logic\Event\HelpRequest\Service;

use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use Psr\Log\LoggerInterface;

/**
 * Verschickt die Bestätigungsmail (Mailvorlage `MailTemplateKey::EventHelpRequestConfirmation`)
 * für eine Helferanmeldung, die beim Absenden automatisch einem Mitglied zugeordnet werden konnte
 * (siehe `EventHelpRequestMemberMatcher`, aufgerufen aus `SubmitEventHelpRequestUseCase`) — bewusst
 * nicht beim nachträglichen manuellen Verknüpfen in der Verwaltung
 * (`LinkEventHelpRequestMemberUseCase`), da die Anmeldung dort schon länger zurückliegt und eine
 * Bestätigung dann nicht mehr passt.
 *
 * Empfänger-Ermittlung siehe `EventHelpRequestRecipientResolver`. Bleibt keine E-Mail-Adresse übrig,
 * wird (best effort, wie der übrige Mailversand) nichts verschickt.
 */
readonly class EventHelpRequestConfirmationMailer
{
    public function __construct(
        private EventHelpRequestRecipientResolver $recipientResolver,
        private NotificationMailer $notificationMailer,
        private LoggerInterface $logger,
    ) {
    }

    public function send(string $firstName, string $lastName, VolunteerEvent $event, ?Member $member, ?string $submittedEmail): void
    {
        try {
            $recipients = $this->recipientResolver->resolve($member, $submittedEmail);
            if ($recipients === []) {
                return;
            }
            $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $event->date);
            $placeholders = [
                'vorname' => $firstName,
                'nachname' => $lastName,
                'veranstaltung' => $event->title,
                'datum' => $parsedDate !== false ? $parsedDate->format('d.m.Y') : $event->date,
                'vereinsname' => AssociationName::CURRENT,
            ];
            foreach ($recipients as $recipient) {
                $this->notificationMailer->sendTo($recipient, MailTemplateKey::EventHelpRequestConfirmation, $placeholders);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Bestätigungsmail für Helferanmeldung konnte nicht vorbereitet werden: {message}', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
