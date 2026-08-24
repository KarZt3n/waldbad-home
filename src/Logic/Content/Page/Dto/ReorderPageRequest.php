<?php

namespace App\Logic\Content\Page\Dto;

readonly class ReorderPageRequest
{
    public function __construct(
        public string $id,
        public ?string $parentId,
        public int $navigationPosition,
        public int $expectedVersion,
    ) {
    }
}
