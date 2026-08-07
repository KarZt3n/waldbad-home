<?php

namespace App\Logic\Content\Page;

use App\Logic\Content\Page\Model\Page;

interface PageProviderInterface
{
    public function findById(string $id): ?Page;

    public function findPublishedBySlug(string $slug): ?Page;

    public function findPublishedById(string $id): ?Page;

    /**
     * @return list<Page>
     */
    public function findAll(): array;

    /**
     * @return list<Page>
     */
    public function findPublishedNavigation(): array;

    /**
     * @return list<Page>
     */
    public function findAllPublished(): array;

    public function slugExists(string $slug, ?string $exceptId = null): bool;
}
