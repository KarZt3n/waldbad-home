<?php

namespace App\Logic\Membership\Member\Dto;

use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Model\ContributionCharge;
use App\Logic\Membership\Member\Model\Remark;

/**
 * Eine Zeile aus einem vollständigen Mitglieder-Export (siehe `RestoreMembersUseCase`). Die
 * Stammdaten stecken in `$data`; `$data->payerMemberNumber` wird dabei nicht verwendet, maßgeblich
 * ist `$data->payerMemberId`.
 */
readonly class RestoreMemberRow
{
    /**
     * @param list<Remark>             $remarks
     * @param list<ContributionCharge> $oneTimeCharges
     */
    public function __construct(
        public string $id,
        public string $memberNumber,
        public CreateMemberRequest $data,
        public bool $emailConsent,
        public ?ContributionCategory $contributionCategory,
        public ?int $contributionAmountCents,
        public ?int $workAssignmentSurchargeCents,
        public array $remarks,
        public array $oneTimeCharges,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $deletedAt,
    ) {
    }
}
