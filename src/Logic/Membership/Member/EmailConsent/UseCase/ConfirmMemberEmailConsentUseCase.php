<?php

namespace App\Logic\Membership\Member\EmailConsent\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Member\EmailConsent\Exception\InvalidMemberEmailConsentTokenException;
use App\Logic\Membership\Member\EmailConsent\Manager\MemberEmailConsentTokenManagerInterface;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;

/**
 * Löst den per Mail verschickten Bestätigungslink ein (siehe `MemberEmailConsentToken`,
 * `SendMemberEmailConsentRequestUseCase`) und setzt `Member::$emailConsent` auf `true`. Ein bereits
 * eingelöster Link wird beim erneuten Aufruf (z. B. Doppelklick, E-Mail-Programm-Vorschau) ohne
 * Fehler wie ein erneuter Erfolg behandelt, statt den Bestätigungsversuch scheitern zu lassen.
 */
readonly class ConfirmMemberEmailConsentUseCase
{
    public function __construct(
        private MemberEmailConsentTokenManagerInterface $tokens,
        private MemberManagerInterface $members,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $rawToken): void
    {
        $now = $this->clock->now();
        $token = $this->tokens->findByHash(hash('sha256', $rawToken)) ?? throw new InvalidMemberEmailConsentTokenException();
        if ($token->isExpired($now)) {
            throw new InvalidMemberEmailConsentTokenException();
        }
        if ($token->isConfirmed()) {
            return;
        }

        $member = $this->members->get($token->memberId);
        $this->members->save($member->withEmailConsent(true));
        $this->tokens->markConfirmed($token->id, $now);
    }
}
