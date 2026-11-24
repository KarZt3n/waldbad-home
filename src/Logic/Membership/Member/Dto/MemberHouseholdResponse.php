<?php

namespace App\Logic\Membership\Member\Dto;

readonly class MemberHouseholdResponse
{
    /**
     * @param MemberResponse       $member           Das angefragte Mitglied mit allen Daten.
     * @param list<MemberResponse> $householdMembers Alle Mitglieder mit derselben Hauptnummer
     *                                                (inklusive des angefragten Mitglieds selbst).
     * @param list<MemberResponse> $payerEntries      Alle Mitglieder, deren Beitrag tatsächlich von
     *                                                $payer bezahlt wird (inklusive $payer selbst,
     *                                                falls dieser Selbstzahler ist).
     */
    public function __construct(
        public MemberResponse $member,
        public MemberResponse $payer,
        public array $householdMembers,
        public array $payerEntries,
        public int $payerTotalAnnualCents,
    ) {
    }
}
