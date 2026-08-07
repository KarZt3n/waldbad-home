<?php

namespace App\Logic\Content\Page\Manager;

use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\Page;

interface PageManagerInterface
{
    public function get(string $id): Page;

    public function getPublished(string $slug): Page;

    public function getPublishedById(string $id): Page;

    /**
     * @return list<Page>
     */
    public function all(): array;

    /**
     * @return list<Page>
     */
    public function navigation(): array;

    /**
     * @return list<Page>
     */
    public function publishedPages(): array;

    public function ensureSlugAvailable(string $slug, ?string $exceptId = null): void;

    public function ensureParentAllowed(?string $parentId, ?string $pageId = null): void;

    /**
     * @param list<ContentBlock> $blocks
     */
    public function ensureEmbeddedPagesAllowed(array $blocks, string $pageId): void;

    public function nextAvailableSlug(string $baseSlug): string;

    public function ensureCanDelete(Page $page): void;

    public function save(Page $page): Page;

    /**
     * @param list<Page> $pages
     * @return list<Page>
     */
    public function saveAll(array $pages): array;

    public function delete(string $id): void;
}
