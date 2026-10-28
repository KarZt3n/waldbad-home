<?php

namespace App\Data\Overview\Adapter;

use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;
use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;

/** Veröffentlichte Seiten, deren Entwurf seitdem geändert, aber nicht erneut veröffentlicht wurde. */
readonly class PagesWithUnpublishedChangesTaskSource implements OverviewTaskSourceInterface
{
    public function __construct(private PageManagerInterface $pages)
    {
    }

    public function type(): OverviewTaskType
    {
        return OverviewTaskType::PagesWithUnpublishedChanges;
    }

    public function openItemDates(): array
    {
        return array_values(array_map(
            static fn (Page $page): \DateTimeImmutable => $page->updatedAt,
            array_filter(
                $this->pages->all(),
                static fn (Page $page): bool => $page->status === PageStatus::Draft && $page->publishedAt !== null,
            ),
        ));
    }
}
