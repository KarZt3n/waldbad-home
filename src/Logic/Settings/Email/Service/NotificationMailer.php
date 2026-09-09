<?php

namespace App\Logic\Settings\Email\Service;

use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Verschickt E-Mails, deren Text aus einer Mailvorlage kommt (siehe `MailTemplateKey`,
 * `MailTemplateRenderer`) — nie fest im Code. Zwei Versandarten:
 *
 * - `notify()`: an die für ein `NotificationEvent` hinterlegten (admin-konfigurierten) Empfänger
 *   (siehe „Einstellungen“ → „E-Mail-Einstellungen“). Erstes Beispiel: `SubmitMembershipApplicationUseCase`
 *   bei einem neuen Mitgliedsantrag.
 * - `sendTo()`: direkt an eine einzelne, aus den Fachdaten stammende Adresse (kein konfigurierbarer
 *   Empfängerkreis) — z. B. die Bestätigungsmail an die antragstellende Person, siehe
 *   `ReleaseMembershipApplicationUseCase`.
 *
 * Bewusst „best effort“: Ist kein Mailserver konfiguriert, das Ereignis ohne Empfänger, oder
 * schlägt der Versand fehl (falsche Zugangsdaten, Server nicht erreichbar, …), wird das nur
 * geloggt — die eigentliche, auslösende Aktion (z. B. die Antragsannahme) darf daran nicht
 * scheitern. Ein sicheres Prüfen der Konfiguration steht Admins über „Testmail senden“
 * (`SendTestEmailUseCase`) separat zur Verfügung, dort wird ein Fehler sehr wohl gemeldet.
 */
readonly class NotificationMailer
{
    public function __construct(
        private EmailSettingsManagerInterface $manager,
        private ConfiguredMailTransportFactory $transportFactory,
        private MailTemplateRenderer $templateRenderer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, string> $placeholders
     */
    public function notify(NotificationEvent $event, MailTemplateKey $templateKey, array $placeholders): void
    {
        $settings = $this->manager->get();
        $recipients = $settings->recipientsFor($event);
        if (!$settings->isConfigured() || $recipients === []) {
            return;
        }

        $this->send($settings, $templateKey, $placeholders, $recipients, $event->value);
    }

    /**
     * @param array<string, string> $placeholders
     */
    public function sendTo(string $toEmail, MailTemplateKey $templateKey, array $placeholders): void
    {
        $settings = $this->manager->get();
        if (!$settings->isConfigured()) {
            return;
        }

        $this->send($settings, $templateKey, $placeholders, [$toEmail], $templateKey->value);
    }

    /**
     * @param list<string> $recipients
     * @param array<string, string> $placeholders
     */
    private function send(EmailSettings $settings, MailTemplateKey $templateKey, array $placeholders, array $recipients, string $logContext): void
    {
        try {
            $rendered = $this->templateRenderer->render($templateKey, $placeholders);
            $transport = $this->transportFactory->create($settings);
            $email = (new Email())
                ->from(new Address((string) $settings->fromAddress, (string) ($settings->fromName ?? '')))
                ->subject($rendered['subject'])
                ->text($rendered['body']);
            foreach ($recipients as $recipient) {
                $email->addTo($recipient);
            }
            $transport->send($email);
        } catch (\Throwable $exception) {
            $this->logger->error('E-Mail für "{context}" konnte nicht gesendet werden: {message}', [
                'context' => $logContext,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
