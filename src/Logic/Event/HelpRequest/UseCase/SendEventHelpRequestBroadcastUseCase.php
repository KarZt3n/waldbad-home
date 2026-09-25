<?php

namespace App\Logic\Event\HelpRequest\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Event\HelpRequest\Dto\EventHelpRequestBroadcastResponse;
use App\Logic\Settings\Email\Service\FreeTextMailer;
use Psr\Log\LoggerInterface;

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
 * Der Rückgabewert hält erfolgreiche und fehlgeschlagene Zustellungen auseinander. Der asynchrone
 * Handler verarbeitet jeweils genau einen Empfänger und signalisiert Messenger einen Fehlschlag,
 * damit dessen Retry-Strategie greift. Direkte Aufrufer können weiterhin mehrere Empfänger in einem
 * Durchlauf verarbeiten, ohne dass ein einzelner Fehler den restlichen Versand abbricht.
 */
readonly class SendEventHelpRequestBroadcastUseCase
{
    public function __construct(
        private FreeTextMailer $mailer,
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

        $sent = 0;
        $failed = 0;
        foreach ($uniqueRecipients as $recipient) {
            try {
                $this->mailer->send($recipient, $trimmedSubject, $trimmedBody);
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
