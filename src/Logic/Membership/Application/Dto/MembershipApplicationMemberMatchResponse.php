<?php

namespace App\Logic\Membership\Application\Dto;

readonly class MembershipApplicationMemberMatchResponse
{
    /**
     * @param list<ApplicantMemberMatchResponse> $applicants
     * @param list<MemberMatchFieldResponse> $bankAccountFields leer, wenn die Bankverbindung keinem Mitglied zugeordnet werden kann
     * @param list<string> $conflictingMemberNumbers
     * @param list<MissingHouseholdMemberResponse> $missingMembers
     */
    public function __construct(
        public string $primaryMemberNumber,
        public array $applicants,
        public ?string $bankAccountMemberNumber,
        public array $bankAccountFields,
        public array $conflictingMemberNumbers,
        public array $missingMembers,
    ) {
    }
}
