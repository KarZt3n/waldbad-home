<?php

namespace App\Logic\Content\Page\Dto;

use App\Logic\Content\Page\Model\ContentBlock;

readonly class CreatePageRequest
{
    /**
     * @param list<ContentBlock> $blocks
     */
    public function __construct(
        public string $title,
        public string $slug,
        public string $navigationLabel,
        public ?string $parentId,
        public array $blocks,
        public bool $visible,
        public bool $showInNavigation,
        public int $navigationPosition,
        public ?string $seoTitle,
        public ?string $seoDescription,
    ) {
    }
}
