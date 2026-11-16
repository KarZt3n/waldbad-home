<?php

namespace App\Logic\Membership\Member\Dto;

readonly class RestoreMembersRequest
{
    /**
     * @param list<RestoreMemberRow> $rows
     */
    public function __construct(
        public array $rows,
    ) {
    }
}
