<?php

namespace App\Logic\Settings\Email\Service;

use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
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
 *
 * Jede Mail geht multipart raus: der reine Vorlagentext (`Email::text()`, als Fallback für Mail-
 * Programme ohne HTML-Unterstützung) sowie derselbe Text gestaltet im Design des Vereins
 * (`Email::html()`, siehe `BrandedEmailLayout`).
 */
readonly class NotificationMailer
{
    private const string ASSOCIATION_NAME = 'Naturbad Borkheide e.V.';

    public function __construct(
        private EmailSettingsManagerInterface $manager,
        private ConfiguredMailTransportFactory $transportFactory,
        private MailTemplateRenderer $templateRenderer,
        private BrandedEmailLayout $layout,
        private EmailLogoProviderInterface $logoProvider,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, string> $placeholders
     * @param array<string, string> $htmlBlocks siehe `MailTemplateRenderer::render()`
     */
    public function notify(NotificationEvent $event, MailTemplateKey $templateKey, array $placeholders, array $htmlBlocks = []): void
    {
        $settings = $this->manager->get();
        $recipients = $settings->recipientsFor($event);
        if (!$settings->isConfigured() || $recipients === []) {
            return;
        }

        $this->send($settings, $templateKey, $placeholders, $htmlBlocks, $recipients, $event->value);
    }

    /**
     * @param array<string, string> $placeholders
     * @param array<string, string> $htmlBlocks siehe `MailTemplateRenderer::render()`
     */
    public function sendTo(string $toEmail, MailTemplateKey $templateKey, array $placeholders, array $htmlBlocks = []): void
    {
        $settings = $this->manager->get();
        if (!$settings->isConfigured()) {
            return;
        }

        $this->send($settings, $templateKey, $placeholders, $htmlBlocks, [$toEmail], $templateKey->value);
    }

    /**
     * @param array<string, string> $placeholders
     * @param array<string, string> $htmlBlocks
     * @param list<string> $recipients
     */
    private function send(EmailSettings $settings, MailTemplateKey $templateKey, array $placeholders, array $htmlBlocks, array $recipients, string $logContext): void
    {
        try {
            $rendered = $this->templateRenderer->render($templateKey, $placeholders, $htmlBlocks);
            $html = $this->layout->wrap($rendered['subject'], $rendered['html'], $this->logoProvider->getLogoDataUri(), self::ASSOCIATION_NAME);
            $transport = $this->transportFactory->create($settings);
            $email = (new Email())
                ->from(new Address((string) $settings->fromAddress, (string) ($settings->fromName ?? '')))
                ->subject($rendered['subject'])
                ->text($rendered['body'])
                ->html($html);
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
