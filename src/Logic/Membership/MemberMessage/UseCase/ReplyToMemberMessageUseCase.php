<?php

namespace App\Logic\Membership\MemberMessage\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\MemberMessage\Dto\ReplyToMemberMessageRequest;
use App\Logic\Membership\MemberMessage\Manager\MemberMessageManagerInterface;
use App\Logic\Settings\Email\Service\FreeTextMailer;

/**
 * Schreibt dem Mitglied zu einer über „Meine Mitgliedschaft" gesendeten Nachricht eine Mail über
 * den Vereinsabsender. Der Status der Nachricht bleibt unverändert — ob sie damit erledigt ist,
 * entscheidet die Verwaltung separat.
 */
readonly class ReplyToMemberMessageUseCase
{
    public function __construct(
        private MemberMessageManagerInterface $messages,
        private FreeTextMailer $mailer,
    ) {
    }

    public function execute(ReplyToMemberMessageRequest $request): void
    {
        $this->messages->get($request->messageId);
        $recipient = trim($request->recipient);
        $subject = trim($request->subject);
        $body = trim($request->body);
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleViolationException('Die Empfänger-E-Mail-Adresse ist ungültig.');
        }
        if ($subject === '' || $body === '') {
            throw new BusinessRuleViolationException('Betreff und Text der Mail sind erforderlich.');
        }

        $this->mailer->send($recipient, $subject, $body);
    }
}
