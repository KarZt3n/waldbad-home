<?php

namespace App\Logic\Content\Page;

use App\Logic\Content\Page\Model\Page;

interface PageProviderInterface
{
    public function findById(string $id): ?Page;

    /**
     * @return list<Page>
     */
    public function findAll(): array;

    /**
     * @return list<Page>
     */
    public function findAllPublished(): array;

    public function slugExists(string $slug, ?string $exceptId = null): bool;
}
