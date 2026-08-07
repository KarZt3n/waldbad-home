<?php

namespace App\Logic\Membership\Application\Dto;

use App\Logic\Membership\Application\Model\MembershipType;

readonly class SubmitMembershipApplicationRequest
{
    /**
     * @param list<ApplicantInput> $applicants
     */
    public function __construct(
        public MembershipType $membershipType,
        public array $applicants,
        public string $accountHolder,
        public string $iban,
        public ?string $bankName,
        public string $signerName,
        public bool $emailConsent,
        public string $declarationVersion,
    ) {
    }
}
