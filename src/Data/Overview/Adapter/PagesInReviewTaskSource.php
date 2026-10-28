<?php

namespace App\Data\Overview\Adapter;

use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;
use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;

readonly class PagesInReviewTaskSource implements OverviewTaskSourceInterface
{
    public function __construct(private PageManagerInterface $pages)
    {
    }

    public function type(): OverviewTaskType
    {
        return OverviewTaskType::PagesInReview;
    }

    public function openItemDates(): array
    {
        return array_values(array_map(
            static fn (Page $page): \DateTimeImmutable => $page->updatedAt,
            array_filter(
                $this->pages->all(),
                static fn (Page $page): bool => $page->status === PageStatus::InReview,
            ),
        ));
    }
}
