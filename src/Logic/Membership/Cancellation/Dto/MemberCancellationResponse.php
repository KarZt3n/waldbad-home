<?php

namespace App\Logic\Membership\Cancellation\Dto;

use App\Logic\Membership\Cancellation\Model\MemberCancellation;
use App\Logic\Membership\Cancellation\Model\MemberCancellationStatus;

readonly class MemberCancellationResponse
{
    public function __construct(
        public string $id,
        public string $memberId,
        public string $memberNumber,
        public string $memberName,
        public string $primaryMemberNumber,
        public string $requestedByEmail,
        public ?string $requestedByName,
        public ?string $requestedByMemberNumber,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $exitDate,
        public ?string $successorMemberId,
        public ?string $successorName,
        public ?string $contactRecipientMemberId,
        public ?string $contactRecipientName,
        public MemberCancellationStatus $status,
        public ?\DateTimeImmutable $decidedAt,
        /** Hinweis nach der Bestätigung, z. B. wenn die Beiträge der Familie nicht neu berechnet werden konnten. */
        public ?string $warning = null,
    ) {
    }

    public static function fromCancellation(MemberCancellation $cancellation, ?string $warning = null): self
    {
        return new self(
            id: $cancellation->id,
            memberId: $cancellation->memberId,
            memberNumber: $cancellation->memberNumber,
            memberName: $cancellation->memberName,
            primaryMemberNumber: $cancellation->primaryMemberNumber,
            requestedByEmail: $cancellation->requestedByEmail,
            requestedByName: $cancellation->requestedByName,
            requestedByMemberNumber: $cancellation->requestedByMemberNumber,
            submittedAt: $cancellation->submittedAt,
            exitDate: $cancellation->exitDate,
            successorMemberId: $cancellation->successorMemberId,
            successorName: $cancellation->successorName,
            contactRecipientMemberId: $cancellation->contactRecipientMemberId,
            contactRecipientName: $cancellation->contactRecipientName,
            status: $cancellation->status,
            decidedAt: $cancellation->decidedAt,
            warning: $warning,
        );
    }
}
