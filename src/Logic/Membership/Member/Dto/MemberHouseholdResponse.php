<?php

namespace App\Logic\Membership\Member\Dto;

readonly class MemberHouseholdResponse
{
    /**
     * @param list<MemberResponse> $householdMembers Alle Mitglieder mit derselben Hauptnummer
     *                                                (inklusive des angefragten Mitglieds selbst).
     * @param list<MemberResponse> $payerEntries      Alle Mitglieder, deren Beitrag tatsächlich von
     *                                                $payer bezahlt wird (inklusive $payer selbst,
     *                                                falls dieser Selbstzahler ist).
     */
    public function __construct(
        public MemberResponse $payer,
        public array $householdMembers,
        public array $payerEntries,
        public int $payerTotalAnnualCents,
    ) {
    }
}
