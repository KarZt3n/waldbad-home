<?php

namespace App\Logic\Membership\Member\Orchestrator;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Mapping\MemberModelFactory;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\MemberNumberGeneratorInterface;
use App\Logic\Membership\Member\Model\ContributionCharge;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Service\MemberContributionCalculator;
use App\Logic\Membership\Member\Service\MemberOneTimeChargeResolver;

/**
 * Bündelt das Anlegen eines Mitglieds (Nummernvergabe, Defaultwerte, Beitragsermittlung,
 * einmalige Gebühren, Persistierung), damit die aufrufenden UseCases (manuelle Anlage, Import,
 * Freigabe eines Mitgliedsantrags) diese Schritte nicht jeweils selbst wiederholen müssen.
 */
readonly class MemberOnboardingOrchestrator
{
    public function __construct(
        private MemberManagerInterface $members,
        private MemberNumberGeneratorInterface $numberGenerator,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private MemberModelFactory $factory,
        private MemberContributionCalculator $calculator,
        private MemberOneTimeChargeResolver $oneTimeCharges,
    ) {
    }

    /**
     * @param bool $chargeOneTimeFees Bei der Übernahme historischer Bestandsmitglieder (Import)
     *                                auf false setzen, damit keine Beitrittsgebühr für längst
     *                                bestehende Mitgliedschaften berechnet wird — sie gilt nur für
     *                                tatsächlich neue Mitgliedschaften.
     */
    public function createFromRequest(CreateMemberRequest $request, bool $chargeOneTimeFees = true): Member
    {
        $memberNumber = $request->memberNumber ?? $this->numberGenerator->next();
        $primaryMemberNumber = $request->primaryMemberNumber ?? $memberNumber;
        if ($primaryMemberNumber !== $memberNumber
            && $request->familyRole !== FamilyRole::None
            && $this->members->findByMemberNumber($primaryMemberNumber) === null
        ) {
            throw new BusinessRuleViolationException(sprintf(
                'Es wurde kein Hauptmitglied mit der Mitgliedsnummer "%s" gefunden.',
                $primaryMemberNumber,
            ));
        }

        $payerMemberId = $request->payerMemberId;
        if ($request->payerMemberNumber !== null) {
            $payerMemberId = ($this->members->findByMemberNumber($request->payerMemberNumber)
                ?? throw new BusinessRuleViolationException(sprintf(
                    'Es wurde kein zahlendes Mitglied mit der Mitgliedsnummer "%s" gefunden.',
                    $request->payerMemberNumber,
                )))->id;
        }

        $mandateReference = $request->mandateReference;
        if ($mandateReference === null && $request->payerType === PayerType::SelfPayer) {
            $mandateReference = $memberNumber;
        }

        $member = $this->factory->createFromRequest(
            request: $request,
            id: $this->identifierGenerator->generate(),
            memberNumber: $memberNumber,
            primaryMemberNumber: $primaryMemberNumber,
            mandateReference: $mandateReference,
            payerMemberId: $payerMemberId,
            nextBookingMonth: $request->nextBookingMonth ?? 3,
            nextBookingYear: $request->nextBookingYear ?? ((int) $request->joinedAt->format('Y') + 1),
        );

        $household = $this->members->findByPrimaryMemberNumber($primaryMemberNumber);
        $outcome = $this->calculator->calculate($member, $household, $this->clock->now());
        $member = $member->withContribution($outcome->category, $outcome->amountCents, $outcome->workAssignmentSurchargeCents);

        if ($chargeOneTimeFees) {
            $now = $this->clock->now();
            foreach ($this->oneTimeCharges->resolveForNewMember($request->familyRole) as $rate) {
                $member = $member->withOneTimeCharge(new ContributionCharge(
                    id: $this->identifierGenerator->generate(),
                    label: $rate->label,
                    amountCents: $rate->amountCents,
                    chargedAt: $now,
                ));
            }
        }

        return $this->members->save($member);
    }
}
