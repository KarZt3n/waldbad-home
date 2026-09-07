<?php

namespace App\Logic\Membership\Member\Dto;

readonly class ImportMembersResponse
{
    /**
     * @param list<ImportRowError> $errors
     */
    public function __construct(
        public int $created,
        public int $updated,
        public array $errors,
    ) {
    }
}
