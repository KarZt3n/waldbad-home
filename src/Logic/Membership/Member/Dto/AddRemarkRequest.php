<?php

namespace App\Logic\Membership\Member\Dto;

readonly class AddRemarkRequest
{
    public function __construct(
        public string $memberId,
        public string $text,
        public ?string $authorDisplayName,
    ) {
    }
}
