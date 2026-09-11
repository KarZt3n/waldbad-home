<?php

namespace App\Logic\Membership\MemberAccess\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateSettingsManagerInterface;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\MemberAccess\Dto\MemberAccessSessionResponse;
use App\Logic\Membership\MemberAccess\Dto\MemberSelfServiceResponse;
use App\Logic\Membership\MemberAccess\Exception\InvalidMemberAccessTokenException;
use App\Logic\Membership\MemberAccess\Manager\MemberAccessTokenManagerInterface;
use App\Logic\Membership\MemberAccess\Service\MemberAccessPasswordHasher;
use App\Logic\Membership\MemberAccess\Service\WorkAssignmentCreditCalculator;

/**
 * Löst einen per Mail verschickten Token in die zugehörigen, nur lesend freigegebenen
 * Mitgliedsdaten auf (siehe `MemberAccessToken`, `MemberSelfServiceResponse`) — wird bei jedem
 * Aufruf aus „Meine Mitgliedschaft" erneut geprüft (Daten laden, Nachricht senden), es gibt keine
 * serverseitige Sitzung darüber hinaus. Das zusätzlich zum Token nötige Passwort (zweiter Faktor,
 * siehe `MemberAccessToken`) wird deshalb ebenfalls bei jedem Aufruf erneut mitgeschickt und
 * geprüft, statt nur einmalig — die Erfolgsprüfung liefert bei falschem Token wie bei falschem
 * Passwort denselben Fehler, um nicht zu verraten, welcher der beiden Faktoren nicht passt.
 */
readonly class ResolveMemberAccessSessionUseCase
{
    public function __construct(
        private MemberAccessTokenManagerInterface $tokens,
        private MemberManagerInterface $members,
        private ContributionRateManagerInterface $contributionRates,
        private ContributionRateSettingsManagerInterface $contributionRateSettings,
        private WorkAssignmentCreditCalculator $workAssignmentCredit,
        private MemberAccessPasswordHasher $passwordHasher,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $rawToken, string $password): MemberAccessSessionResponse
    {
        $token = $this->tokens->findByHash(hash('sha256', $rawToken)) ?? throw new InvalidMemberAccessTokenException();
        if ($token->isExpired($this->clock->now())) {
            throw new InvalidMemberAccessTokenException();
        }
        if (!$this->passwordHasher->verify($password, $token->passwordHash)) {
            throw new InvalidMemberAccessTokenException();
        }

        $members = $this->members->findByPrimaryMemberNumber($token->primaryMemberNumber);
        if ($members === []) {
            throw new InvalidMemberAccessTokenException();
        }

        return new MemberAccessSessionResponse(
            email: $token->email,
            members: array_map(
                fn (Member $member): MemberSelfServiceResponse => MemberSelfServiceResponse::fromMember($member, $this->contributionCategoryLabel($member)),
                $members,
            ),
            contributionRatesValidFrom: $this->contributionRateSettings->get()->validFrom?->format('Y-m-d'),
            workAssignmentCredit: $this->workAssignmentCredit->calculate($members),
        );
    }

    private function contributionCategoryLabel(Member $member): ?string
    {
        return $member->contributionCategory !== null
            ? $this->contributionRates->findByCategory($member->contributionCategory)?->label
            : null;
    }
}
