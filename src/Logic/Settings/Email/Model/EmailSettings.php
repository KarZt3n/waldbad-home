<?php

namespace App\Logic\Settings\Email\Model;

/**
 * Einzeiliger Einstellungs-Datensatz (Singleton): SMTP-Zugangsdaten für den Mailversand sowie, je
 * `NotificationEvent`, die E-Mail-Adressen, die dabei benachrichtigt werden sollen.
 *
 * Das Passwort wird bewusst im Klartext gespeichert (nicht gehasht wie beim PIN, siehe
 * `PinSettings`) — der Mailserver braucht es im Klartext, um sich damit anzumelden, ein Hash wäre
 * dafür unbrauchbar. Es verlässt diesen Datensatz aber nie über die API (siehe
 * `EmailSettingsResponse`, die nur `passwordIsSet` liefert).
 */
readonly class EmailSettings
{
    /**
     * @param array<string, list<string>> $notificationRecipients Schlüssel = `NotificationEvent::$value`
     */
    public function __construct(
        public ?EmailProviderPreset $provider,
        public ?string $host,
        public ?int $port,
        public ?string $username,
        public ?string $password,
        public ?string $fromAddress,
        public ?string $fromName,
        public array $notificationRecipients,
    ) {
    }

    /**
     * Ob genug hinterlegt ist, um überhaupt einen Versandversuch zu unternehmen. Benutzername/
     * Passwort sind bewusst nicht zwingend (z. B. IP-basierte SMTP-Relays kommen ohne aus).
     */
    public function isConfigured(): bool
    {
        return $this->host !== null && trim($this->host) !== ''
            && $this->fromAddress !== null && trim($this->fromAddress) !== '';
    }

    /**
     * @return list<string>
     */
    public function recipientsFor(NotificationEvent $event): array
    {
        return $this->notificationRecipients[$event->value] ?? [];
    }

    public function withConnection(
        ?EmailProviderPreset $provider,
        ?string $host,
        ?int $port,
        ?string $username,
        ?string $password,
        ?string $fromAddress,
        ?string $fromName,
    ): self {
        return new self($provider, $host, $port, $username, $password, $fromAddress, $fromName, $this->notificationRecipients);
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

        return new self($this->provider, $this->host, $this->port, $this->username, $this->password, $this->fromAddress, $this->fromName, $notificationRecipients);
    }
}
