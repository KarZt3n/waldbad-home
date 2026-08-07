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
     * @return list<PageResponse>
     */
    public function execute(): array
    {
        return array_map(PageResponse::fromPage(...), $this->manager->all());
    }
}
