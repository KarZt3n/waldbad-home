<?php

namespace App\Logic\Content\Page\Query;

use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Manager\PageManagerInterface;

readonly class GetPublishedPageQuery
{
    public function __construct(private PageManagerInterface $manager)
    {
    }

    public function execute(string $slug): PageResponse
    {
        return PageResponse::fromPage($this->manager->getPublished($slug));
    }
}
