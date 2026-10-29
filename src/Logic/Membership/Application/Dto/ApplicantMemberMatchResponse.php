<?php

namespace App\Logic\Membership\Application\Dto;

readonly class ApplicantMemberMatchResponse
{
    /**
     * @param list<MemberMatchFieldResponse> $fields
     */
    public function __construct(
        public int $position,
        public string $applicantName,
        public ?string $memberId,
        public ?string $memberNumber,
        public ?string $memberName,
        public array $fields,
    ) {
    }

    public function isNewPerson(): bool
    {
        return $this->memberId === null;
    }
}
