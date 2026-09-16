<?php

namespace App\Logic\Settings\Email\Model;

/**
 * Einzeiliger Einstellungs-Datensatz (Singleton): je `NotificationEvent`, die E-Mail-Adressen, die
 * dabei benachrichtigt werden sollen. Die SMTP-Zugangsdaten selbst kommen aus `MAILER_DSN`
 * (Deployment-Konfiguration, siehe `NotificationMailer`) statt aus der Datenbank.
 */
readonly class EmailSettings
{
    /**
     * @param array<string, list<string>> $notificationRecipients Schlüssel = `NotificationEvent::$value`
     */
    public function __construct(
        public array $notificationRecipients,
    ) {
    }

    /**
     * @return list<string>
     */
    public function recipientsFor(NotificationEvent $event): array
    {
        return $this->notificationRecipients[$event->value] ?? [];
    }

    /**
     * @param list<string> $recipients
     */
    public function withRecipientsFor(NotificationEvent $event, array $recipients): self
    {
        $notificationRecipients = $this->notificationRecipients;
        if ($recipients === []) {
            unset($notificationRecipients[$event->value]);
        } else {
            $notificationRecipients[$event->value] = $recipients;
        }

        return new self($notificationRecipients);
    }
}
