<?php

namespace App\Logic\Content\Page\Dto;

use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;

readonly class PageResponse
{
    /**
     * @param list<ContentBlock> $blocks
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public string $navigationLabel,
        public ?string $parentId,
        public array $blocks,
        public PageStatus $status,
        public bool $visible,
        public bool $showInNavigation,
        public int $navigationPosition,
        public ?string $seoTitle,
        public ?string $seoDescription,
        public int $version,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $publishedAt,
    ) {
    }

    public static function fromPage(Page $page): self
    {
        return new self(
            id: $page->id,
            title: $page->title,
            slug: $page->slug,
            navigationLabel: $page->navigationLabel,
            parentId: $page->parentId,
            blocks: $page->blocks,
            status: $page->status,
            visible: $page->visible,
            showInNavigation: $page->showInNavigation,
            navigationPosition: $page->navigationPosition,
            seoTitle: $page->seoTitle,
            seoDescription: $page->seoDescription,
            version: $page->version,
            createdAt: $page->createdAt,
            updatedAt: $page->updatedAt,
            publishedAt: $page->publishedAt,
        );
    }
}
