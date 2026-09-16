<?php

namespace App\Logic\Settings\Email\Service;

use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
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
 * Der Versandweg selbst ist der reguläre, per `MAILER_DSN` konfigurierte `symfony/mailer`
 * (`config/packages/mailer.yaml`) — keine eigene, admin-editierbare SMTP-Konfiguration mehr: eine
 * fehlende/unvollständige Datenbankzeile konnte sonst jeden Versand ohne jede Fehlermeldung
 * verschlucken. Absenderadresse/-name kommen ebenso aus der Deployment-Konfiguration
 * (`MAILER_FROM_ADDRESS`/`MAILER_FROM_NAME`, siehe `config/services.yaml`).
 *
 * Bewusst „best effort“: Ist das Ereignis ohne Empfänger, oder schlägt der eigentliche Versand fehl
 * (Server nicht erreichbar, Zugangsdaten falsch, …), wird das nur geloggt — die eigentliche,
 * auslösende Aktion (z. B. die Antragsannahme) darf daran nicht scheitern.
 *
 * Jede Mail geht multipart raus: der reine Vorlagentext (`Email::text()`, als Fallback für Mail-
 * Programme ohne HTML-Unterstützung) sowie derselbe Text gestaltet im Design des Vereins
 * (`Email::html()`, siehe `BrandedEmailLayout`).
 */
readonly class NotificationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private EmailSettingsManagerInterface $manager,
        private MailTemplateRenderer $templateRenderer,
        private BrandedEmailLayout $layout,
        private EmailLogoProviderInterface $logoProvider,
        private LoggerInterface $logger,
        private string $fromAddress,
        private ?string $fromName,
    ) {
    }

    /**
     * @param array<string, string> $placeholders
     * @param array<string, string> $htmlBlocks siehe `MailTemplateRenderer::render()`
     */
    public function notify(NotificationEvent $event, MailTemplateKey $templateKey, array $placeholders, array $htmlBlocks = []): void
    {
        $recipients = $this->manager->get()->recipientsFor($event);
        if ($recipients === []) {
            return;
        }

        $this->send($templateKey, $placeholders, $htmlBlocks, $recipients, $event->value);
    }

    /**
     * @param array<string, string> $placeholders
     * @param array<string, string> $htmlBlocks siehe `MailTemplateRenderer::render()`
     */
    public function sendTo(string $toEmail, MailTemplateKey $templateKey, array $placeholders, array $htmlBlocks = []): void
    {
        $this->send($templateKey, $placeholders, $htmlBlocks, [$toEmail], $templateKey->value);
    }

    /**
     * @param array<string, string> $placeholders
     * @param array<string, string> $htmlBlocks
     * @param list<string> $recipients
     */
    private function send(MailTemplateKey $templateKey, array $placeholders, array $htmlBlocks, array $recipients, string $logContext): void
    {
        try {
            $rendered = $this->templateRenderer->render($templateKey, $placeholders, $htmlBlocks);
            $html = $this->layout->wrap($rendered['subject'], $rendered['html'], $this->logoProvider->getLogoDataUri(), AssociationName::CURRENT);
            $email = (new Email())
                ->from(new Address($this->fromAddress, $this->fromName ?? ''))
                ->subject($rendered['subject'])
                ->text($rendered['body'])
                ->html($html);
            foreach ($recipients as $recipient) {
                $email->addTo($recipient);
            }
            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            $this->logger->error('E-Mail für "{context}" konnte nicht gesendet werden: {message}', [
                'context' => $logContext,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
