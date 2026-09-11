<?php

namespace App\Logic\Event\HelpRequest\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Event\HelpRequest\Dto\EventHelpRequestBroadcastResponse;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\Email\Service\ConfiguredMailTransportFactory;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
use App\Logic\Settings\MailTemplate\Service\MailContentRenderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Verschickt eine frei formulierte Rundmail (Betreff/Text von der Verwaltung selbst getippt, keine
 * Mailvorlage) an die im Dialog „An:" stehenden Helfer einer Veranstaltung — z. B. für wichtige
 * Hinweise zum Treffpunkt oder als Erinnerung kurz vor dem Termin (siehe „Veranstaltungshelfer" in
 * `assets/app.js`).
 *
 * Anders als noch beim Öffnen des Dialogs (siehe `GetEventHelpRequestBroadcastRecipientsUseCase`,
 * das dort nur eine Vorbelegung liefert) wird hier keine Empfänger-Ermittlung mehr vorgenommen: Die
 * Verwaltung hat die vorbelegte Liste ggf. im „An:"-Feld angepasst (Adressen entfernt oder
 * händisch ergänzt), und genau diese — bereits vom Frontend deduplizierte, aber hier sicherheitshalber
 * nochmals geprüfte — Liste wird angeschrieben.
 *
 * Anders als der übrige, „best effort" arbeitende Mailversand (siehe `NotificationMailer`) wird ein
 * Fehlschlag hier nicht verschluckt: Die Verwaltung löst diese Mail bewusst und einmalig aus und
 * soll erfahren, ob sie tatsächlich ankam (siehe `sentCount`/`failedCount` in der Antwort sowie das
 * Vorbild `SendTestEmailUseCase`) — nur ein Fehlschlag bei einzelnen Empfängern bricht dabei nicht
 * gleich den gesamten restlichen Versand ab.
 */
readonly class SendEventHelpRequestBroadcastUseCase
{
    public function __construct(
        private EmailSettingsManagerInterface $emailSettingsManager,
        private ConfiguredMailTransportFactory $transportFactory,
        private MailContentRenderer $contentRenderer,
        private BrandedEmailLayout $layout,
        private EmailLogoProviderInterface $logoProvider,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string> $recipients Die im „An:"-Feld stehenden E-Mail-Adressen — nicht leer,
     *                                 jede muss ein gültiges Format haben.
     */
    public function execute(string $subject, string $body, array $recipients): EventHelpRequestBroadcastResponse
    {
        $trimmedSubject = trim($subject);
        $trimmedBody = trim($body);
        if ($trimmedSubject === '' || $trimmedBody === '') {
            throw new BusinessRuleViolationException('Betreff und Text der Mail sind erforderlich.');
        }

        $uniqueRecipients = [];
        foreach ($recipients as $recipient) {
            $trimmedRecipient = trim($recipient);
            if ($trimmedRecipient === '' || filter_var($trimmedRecipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new BusinessRuleViolationException(sprintf('„%s" ist keine gültige E-Mail-Adresse.', $trimmedRecipient));
            }
            $uniqueRecipients[mb_strtolower($trimmedRecipient)] = $trimmedRecipient;
        }
        $uniqueRecipients = array_values($uniqueRecipients);
        if ($uniqueRecipients === []) {
            throw new BusinessRuleViolationException('Es ist keine E-Mail-Adresse als Empfänger angegeben.');
        }

        $settings = $this->emailSettingsManager->get();
        // Wirft eine passende Exception, falls kein Mailserver eingerichtet ist.
        $transport = $this->transportFactory->create($settings);

        $html = $this->layout->wrap(
            $trimmedSubject,
            $this->contentRenderer->toHtmlFragment($trimmedBody),
            $this->logoProvider->getLogoDataUri(),
            AssociationName::CURRENT,
        );

        $sent = 0;
        $failed = 0;
        foreach ($uniqueRecipients as $recipient) {
            try {
                $email = (new Email())
                    ->from(new Address((string) $settings->fromAddress, (string) ($settings->fromName ?? '')))
                    ->to($recipient)
                    ->subject($trimmedSubject)
                    ->text($trimmedBody)
                    ->html($html);
                $transport->send($email);
                ++$sent;
            } catch (\Throwable $exception) {
                ++$failed;
                $this->logger->error('Rundmail an Helfer konnte nicht an {recipient} gesendet werden: {message}', [
                    'recipient' => $recipient,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return new EventHelpRequestBroadcastResponse(
            recipientCount: count($uniqueRecipients),
            sentCount: $sent,
            failedCount: $failed,
        );
    }
}
