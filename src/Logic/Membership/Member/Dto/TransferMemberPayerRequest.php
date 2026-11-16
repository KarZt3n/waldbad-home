<?php

namespace App\Logic\Membership\Member\Dto;

readonly class TransferMemberPayerRequest
{
    public function __construct(
        public string $memberId,
        public string $successorMemberId,
        public bool $keepSuccessorPaymentDetails,
        public bool $transferPrimaryMembership,
    ) {
    }
}
