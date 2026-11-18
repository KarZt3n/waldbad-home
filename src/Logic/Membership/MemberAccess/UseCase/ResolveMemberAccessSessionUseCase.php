<?php

namespace App\Logic\Membership\MemberAccess\UseCase;

use App\Logic\Membership\Member\Exception\MemberNotFoundException;
use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Cancellation\Manager\MemberCancellationManagerInterface;
use App\Logic\Membership\Cancellation\Service\CancellationExitDate;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateSettingsManagerInterface;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\MemberAccess\Dto\MemberAccessSessionResponse;
use App\Logic\Membership\MemberAccess\Dto\MemberSelfServiceResponse;
use App\Logic\Membership\MemberAccess\Exception\InvalidMemberAccessTokenException;
use App\Logic\Membership\MemberAccess\Manager\MemberAccessTokenManagerInterface;
use App\Logic\Membership\MemberAccess\Service\MemberAccessPasswordHasher;
use App\Logic\Membership\MemberAccess\Service\MemberPayerGroupResolver;
use App\Logic\Membership\MemberAccess\Service\WorkAssignmentCreditCalculator;

/**
 * Löst einen per Mail verschickten Token in die zugehörigen, nur lesend freigegebenen
 * Mitgliedsdaten auf (siehe `MemberAccessToken`, `MemberSelfServiceResponse`) — wird bei jedem
 * Aufruf aus „Meine Mitgliedschaft" erneut geprüft (Daten laden, Nachricht senden), es gibt keine
 * serverseitige Sitzung darüber hinaus. Das zusätzlich zum Token nötige Passwort (zweiter Faktor,
 * siehe `MemberAccessToken`) wird deshalb ebenfalls bei jedem Aufruf erneut mitgeschickt und
 * geprüft, statt nur einmalig — die Erfolgsprüfung liefert bei falschem Token wie bei falschem
 * Passwort denselben Fehler, um nicht zu verraten, welcher der beiden Faktoren nicht passt.
 *
 * Erreichbar sind nur der Zahler und die Mitglieder, für die er zahlt (siehe `MemberPayerGroupResolver`)
 * — ein Selbstzahler im selben Haushalt nicht. Davon nur aktive Mitglieder: Ausgetretene erscheinen weder in der Anzeige
 * noch lassen sie sich über die darauf aufbauenden Use Cases (Kontaktdaten, Einwilligung, Nachricht)
 * ansprechen. Ist niemand mehr aktiv — z. B. Austritt nach dem Versand des Links —, gilt der Zugang
 * als ungültig.
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
        private MemberPayerGroupResolver $payerGroups,
        private MemberCancellationManagerInterface $cancellations,
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

        $now = $this->clock->now();
        $members = array_values(array_filter(
            $token->payerMemberId !== null
                ? $this->payerGroups->members($token->payerMemberId)
                : $this->members->findByPrimaryMemberNumber($token->primaryMemberNumber),
            static fn (Member $member): bool => $member->isActive($now),
        ));
        if ($members === []) {
            throw new InvalidMemberAccessTokenException();
        }

        $openCancellations = $this->cancellations->openByMemberId();

        return new MemberAccessSessionResponse(
            email: $token->email,
            members: array_map(
                function (Member $member) use ($members, $openCancellations, $now): MemberSelfServiceResponse {
                    return MemberSelfServiceResponse::fromMember(
                        $member,
                        $this->contributionCategoryLabel($member),
                        $now,
                        isset($openCancellations[$member->id]) ? $openCancellations[$member->id]->exitDate->format('Y-m-d') : null,
                        $this->payerGroups->payerIdOf($member),
                        $this->payerName($member, $members),
                    );
                },
                $members,
            ),
            contributionRatesValidFrom: $this->contributionRateSettings->get()->validFrom?->format('Y-m-d'),
            workAssignmentCredit: $this->workAssignmentCredit->calculate($members),
            cancellationExitDate: CancellationExitDate::for($now)->format('Y-m-d'),
        );
    }

    private function contributionCategoryLabel(Member $member): ?string
    {
        return $member->contributionCategory !== null
            ? $this->contributionRates->findByCategory($member->contributionCategory)?->label
            : null;
    }

    /**
     * @param list<Member> $members
     */
    private function payerName(Member $member, array $members): ?string
    {
        $payerId = $this->payerGroups->payerIdOf($member);
        if ($payerId === $member->id) {
            return null;
        }
        foreach ($members as $candidate) {
            if ($candidate->id === $payerId) {
                return $candidate->firstName.' '.$candidate->lastName;
            }
        }
        try {
            $payer = $this->members->get($payerId);
        } catch (MemberNotFoundException) {
            return null;
        }

        return $payer->firstName.' '.$payer->lastName;
    }

}
