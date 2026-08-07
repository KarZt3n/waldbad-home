<?php

namespace App\Logic\Content\Page\Query;

use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Manager\PageManagerInterface;

readonly class GetPublishedPageByIdQuery
{
    public function __construct(private PageManagerInterface $manager)
    {
    }

    public function execute(string $id): PageResponse
    {
        return PageResponse::fromPage($this->manager->getPublishedById($id));
    }
}
