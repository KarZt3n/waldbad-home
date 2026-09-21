<?php

namespace App\Logic\Membership\Member\Query;

use App\Logic\Membership\Member\Dto\MemberHouseholdResponse;
use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;

/**
 * Liefert zwei getrennte Sichten auf ein Mitglied, die nicht zwangsläufig deckungsgleich sind:
 * den Haushalt (alle Mitglieder mit derselben Hauptnummer, also die Familienzugehörigkeit) und die
 * tatsächliche Zahler-Zuordnung (wer den Beitrag welcher Mitglieder trägt). Ein Zahler kann für
 * mehrere Mitglieder desselben Haushalts zahlen; die Summe aus Beitrag und Arbeitseinsatz-Zuschlag
 * aller dieser Mitglieder ergibt die vom Zahler insgesamt zu tragende jährliche Summe.
 */
readonly class GetMemberHouseholdQuery
{
    public function __construct(private MemberManagerInterface $manager)
    {
    }

    public function execute(string $id): MemberHouseholdResponse
    {
        $member = $this->manager->get($id);

        $household = $this->manager->findByPrimaryMemberNumber($member->primaryMemberNumber);
        usort(
            $household,
            static fn (Member $left, Member $right): int => self::sortRank($left) <=> self::sortRank($right)
                ?: $left->birthDate <=> $right->birthDate,
        );

        $payer = $member;
        if ($member->payerType !== PayerType::SelfPayer && $member->payerMemberId !== null) {
            $payer = $this->manager->get($member->payerMemberId);
        }
        $paidByPayer = $this->manager->findByPayerMemberId($payer->id);
        $payerEntries = $payer->payerType === PayerType::SelfPayer ? [$payer, ...$paidByPayer] : $paidByPayer;

        $total = 0;
        foreach ($payerEntries as $entry) {
            $total += ($entry->contributionAmountCents ?? 0) + ($entry->workAssignmentSurchargeCents ?? 0);
        }

        return new MemberHouseholdResponse(
            payer: MemberResponse::fromMember($payer),
            householdMembers: array_map(MemberResponse::fromMember(...), $household),
            payerEntries: array_map(MemberResponse::fromMember(...), $payerEntries),
            payerTotalAnnualCents: $total,
        );
    }

    /**
     * `None` steht hier absichtlich hinter `Head`/`Partner`, nicht auf derselben Stufe: Ein
     * Haushaltsmitglied kann `familyRole: None` nicht nur als „echte" Einzelperson haben, sondern
     * auch, weil ein ehemaliges Kind laut Beitragsordnung automatisch dorthin gewechselt ist (siehe
     * `MemberContributionCalculator::resolveFamilyRole()`) — es soll dann weiterhin eher bei den
     * (verbliebenen) Kindern als vor dem eigentlichen Hauptmitglied/Partner einsortiert werden.
     */
    private static function sortRank(Member $member): int
    {
        return match ($member->familyRole) {
            FamilyRole::Head => 0,
            FamilyRole::Partner => 1,
            FamilyRole::None => 2,
            FamilyRole::Child => 3,
        };
    }
}
