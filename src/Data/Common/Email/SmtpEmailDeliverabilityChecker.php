<?php

namespace App\Data\Common\Email;

use App\Logic\Common\EmailDeliverabilityCheckerInterface;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\RFCValidation;
use SMTPValidateEmail\Exceptions\Exception as SmtpValidationException;
use SMTPValidateEmail\Validator as SmtpValidator;

/**
 * Zweistufige Prüfung, bewusst mehr als ein Format-Regex:
 *
 * 1. `egulias/email-validator` (bereits Abhängigkeit von `symfony/mailer`): RFC-Syntax plus ein
 *    echter DNS-Abgleich der Domain (MX-, ersatzweise A-/AAAA-Eintrag). Reservierte Test-TLDs
 *    (u. a. .test, .example, .invalid) gelten dabei absichtlich als nicht zustellbar.
 * 2. `zytzagoo/smtp-validate-email` (neue, dedizierte Composer-Abhängigkeit für genau diesen
 *    Zweck): baut einen echten SMTP-Dialog mit dem zuständigen Mailserver auf (EHLO/MAIL FROM/
 *    RCPT TO, ohne DATA — es wird also nie tatsächlich etwas verschickt) und liest dessen Antwort
 *    auf die konkrete Adresse. Das erkennt z. B. eine erfundene Adresse auf einer real
 *    existierenden Domain (reine DNS-Prüfung allein würde das nicht tun, siehe Google/Gmail: deren
 *    Server lehnen unbekannte Postfächer beim RCPT-Schritt tatsächlich mit 550 ab).
 *
 * Stufe 2 ist bewusst „best effort“: Kann keine Verbindung aufgebaut werden (z. B. weil ausgehende
 * Verbindungen auf Port 25 in dieser Hosting-Umgebung blockiert sind) oder antwortet der Server gar
 * nicht eindeutig, wird das NICHT als Ablehnung gewertet (`no_conn_is_valid`/`no_comm_is_valid` auf
 * `true`) — eine nicht erreichbare oder unauskunftsfreudige Gegenstelle ist kein Beweis für eine
 * ungültige Adresse, und eine Vereins-Beitrittserklärung darf daran nicht scheitern.
 */
readonly class SmtpEmailDeliverabilityChecker implements EmailDeliverabilityCheckerInterface
{
    private const string PROBE_SENDER = 'postmaster@waldbad-borkheide.de';
    private const int CONNECT_TIMEOUT_SECONDS = 8;

    public function isDeliverable(string $email): bool
    {
        if (!(new EmailValidator())->isValid($email, new MultipleValidationWithAnd([
            new RFCValidation(),
            new DNSCheckValidation(),
        ]))) {
            return false;
        }

        return $this->isMailboxAccepted($email);
    }

    private function isMailboxAccepted(string $email): bool
    {
        $validator = new SmtpValidator();
        $validator->no_conn_is_valid = true;
        $validator->no_comm_is_valid = true;
        $validator->setConnectTimeout(self::CONNECT_TIMEOUT_SECONDS);

        try {
            $results = $validator->validate([$email], self::PROBE_SENDER);
        } catch (SmtpValidationException) {
            // Siehe Klassenkommentar: eine gescheiterte Verbindung ist kein Beweis für eine
            // ungültige Adresse.
            return true;
        }

        return !isset($results[$email]) || $results[$email] !== false;
    }
}
