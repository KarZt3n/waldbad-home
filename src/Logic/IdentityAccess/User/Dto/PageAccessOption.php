<?php

namespace App\Logic\IdentityAccess\User\Dto;

readonly class PageAccessOption
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $parentId,
        public int $navigationPosition,
    ) {
    }
}
