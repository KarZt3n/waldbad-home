<?php

namespace App\Logic\Membership\Member\EmailConsent\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Common\SecureTokenGeneratorInterface;
use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\EmailConsent\Manager\MemberEmailConsentTokenManagerInterface;
use App\Logic\Membership\Member\EmailConsent\MemberEmailConsentLinkBuilderInterface;
use App\Logic\Membership\Member\EmailConsent\Model\MemberEmailConsentToken;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

/**
 * Von der Redaktion ausgelöst (siehe „Mitgliederverwaltung“ → Mitglied → Kontaktdaten): verschickt
 * einen Doppel-Opt-in-Bestätigungslink an die hinterlegte E-Mail-Adresse eines Mitglieds, über den
 * dieses den Erhalt von Vereinsinformationen per E-Mail selbst bestätigen kann (siehe
 * `ConfirmMemberEmailConsentUseCase`) — anders als das beim Mitgliedsantrag gesetzte Häkchen
 * (`Member::$emailConsent`, siehe `ReleaseMembershipApplicationUseCase`) eine vom Mitglied selbst
 * bestätigte, rechtlich robustere Einwilligung.
 */
readonly class SendMemberEmailConsentRequestUseCase
{
    private const int VALIDITY_DAYS = 7;

    public function __construct(
        private MemberManagerInterface $members,
        private MemberEmailConsentTokenManagerInterface $tokens,
        private SecureTokenGeneratorInterface $tokenGenerator,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private NotificationMailer $notificationMailer,
        private MemberEmailConsentLinkBuilderInterface $linkBuilder,
    ) {
    }

    public function execute(string $memberId): MemberResponse
    {
        $member = $this->members->get($memberId);
        if ($member->email === null || trim($member->email) === '') {
            throw new BusinessRuleViolationException('Für dieses Mitglied ist keine E-Mail-Adresse hinterlegt.');
        }

        $rawToken = $this->tokenGenerator->generate();
        $this->tokens->save(new MemberEmailConsentToken(
            id: $this->identifierGenerator->generate(),
            memberId: $member->id,
            email: $member->email,
            tokenHash: hash('sha256', $rawToken),
            expiresAt: $this->clock->now()->modify('+'.self::VALIDITY_DAYS.' days'),
        ));

        $this->notificationMailer->sendTo($member->email, MailTemplateKey::MemberEmailConsentRequest, [
            'vorname' => $member->firstName,
            'link' => $this->linkBuilder->build($rawToken),
            'gueltig_tage' => (string) self::VALIDITY_DAYS,
            'vereinsname' => AssociationName::CURRENT,
        ]);

        return MemberResponse::fromMember($member);
    }
}
