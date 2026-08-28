<?php

namespace App\Logic\Content\Page\Query;

use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Manager\PageManagerInterface;

readonly class ListPagesQuery
{
    public function __construct(private PageManagerInterface $manager)
    {
    }

    /**
     * @param list<string>|null $pageIds
     * @return list<PageResponse>
     */
    public function execute(?array $pageIds = null): array
    {
        $pages = $this->manager->all();
        if ($pageIds !== null) {
            $allowed = array_fill_keys($pageIds, true);
            $pages = array_values(array_filter(
                $pages,
                static fn (\App\Logic\Content\Page\Model\Page $page): bool => isset($allowed[$page->id]),
            ));
        }

        return array_map(PageResponse::fromPage(...), $pages);
    }
}
