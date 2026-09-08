<?php

namespace App\Logic\Membership\Member\Dto;

readonly class ImportRowError
{
    public function __construct(
        public int $rowNumber,
        public string $message,
    ) {
    }
}
