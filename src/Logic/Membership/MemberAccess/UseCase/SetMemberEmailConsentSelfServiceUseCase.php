<?php

namespace App\Logic\Membership\MemberAccess\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Common\SecureTokenGeneratorInterface;
use App\Logic\Membership\Member\EmailConsent\Manager\MemberEmailConsentTokenManagerInterface;
use App\Logic\Membership\Member\EmailConsent\MemberEmailConsentLinkBuilderInterface;
use App\Logic\Membership\Member\EmailConsent\Model\MemberEmailConsentToken;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\MemberAccess\Dto\MemberAccessSessionResponse;
use App\Logic\Membership\MemberAccess\Exception\InvalidMemberAccessTokenException;
use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Setzt die E-Mail-Einwilligung eines über „Meine Mitgliedschaft“ erreichbaren Mitglieds direkt
 * (kein Doppel-Opt-in nötig — der Zugriff über Token+Passwort beweist bereits die Kontrolle über die
 * Adresse, siehe `MemberAccessToken`). Beim Abbestellen (`$granted = false`) geht zusätzlich eine
 * „Schade..."-Mail mit einem Rückgängig-Link raus, der über denselben Bestätigungsmechanismus wie
 * `SendMemberEmailConsentRequestUseCase`/`ConfirmMemberEmailConsentUseCase` läuft — ein Klick darauf
 * setzt die Einwilligung wieder auf `true`, falls das Abbestellen ein Versehen war.
 */
readonly class SetMemberEmailConsentSelfServiceUseCase
{
    private const int UNDO_LINK_VALIDITY_DAYS = 7;

    public function __construct(
        private ResolveMemberAccessSessionUseCase $resolveSession,
        private MemberManagerInterface $members,
        private MemberEmailConsentTokenManagerInterface $consentTokens,
        private SecureTokenGeneratorInterface $tokenGenerator,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private NotificationMailer $notificationMailer,
        private MemberEmailConsentLinkBuilderInterface $linkBuilder,
    ) {
    }

    public function execute(string $rawToken, string $password, string $memberId, bool $granted): MemberAccessSessionResponse
    {
        $session = $this->resolveSession->execute($rawToken, $password);
        $target = null;
        foreach ($session->members as $candidate) {
            if ($candidate->id === $memberId) {
                $target = $candidate;
                break;
            }
        }
        if ($target === null) {
            throw new InvalidMemberAccessTokenException();
        }

        $member = $this->members->get($memberId);
        $member = $this->members->save($member->withEmailConsent($granted));

        if (!$granted && $member->email !== null) {
            $rawUndoToken = $this->tokenGenerator->generate();
            $this->consentTokens->save(new MemberEmailConsentToken(
                id: $this->identifierGenerator->generate(),
                memberId: $member->id,
                email: $member->email,
                tokenHash: hash('sha256', $rawUndoToken),
                expiresAt: $this->clock->now()->modify('+'.self::UNDO_LINK_VALIDITY_DAYS.' days'),
            ));
            $this->notificationMailer->sendTo($member->email, MailTemplateKey::MemberEmailConsentOptOutRegret, [
                'vorname' => $member->firstName,
                'link' => $this->linkBuilder->build($rawUndoToken),
                'gueltig_tage' => (string) self::UNDO_LINK_VALIDITY_DAYS,
                'vereinsname' => AssociationName::CURRENT,
            ]);
        }

        return $this->resolveSession->execute($rawToken, $password);
    }
}
