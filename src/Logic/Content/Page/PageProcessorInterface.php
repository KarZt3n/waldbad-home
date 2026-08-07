<?php

namespace App\Logic\Content\Page;

use App\Logic\Content\Page\Model\Page;

interface PageProcessorInterface
{
    public function save(Page $page): Page;

    /**
     * @param list<Page> $pages
     * @return list<Page>
     */
    public function saveAll(array $pages): array;

    public function delete(string $id): void;
}
