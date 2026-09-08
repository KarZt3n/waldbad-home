<?php

namespace App\Logic\Membership\Member\Dto;

readonly class MemberRecalculationError
{
    public function __construct(
        public string $memberNumber,
        public string $message,
    ) {
    }
}
