<?php

namespace App\Logic\Membership\Member\Dto;

readonly class RecalculateAllContributionsResponse
{
    /**
     * @param list<MemberRecalculationError> $errors
     */
    public function __construct(
        public int $updated,
        public array $errors,
    ) {
    }
}
