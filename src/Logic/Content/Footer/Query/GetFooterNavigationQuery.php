<?php

namespace App\Logic\Content\Footer\Query;

use App\Logic\Content\Footer\Dto\FooterNavigationResponse;
use App\Logic\Content\Footer\Dto\FooterPageResponse;
use App\Logic\Content\Footer\Manager\FooterNavigationManagerInterface;
use App\Logic\Content\Footer\Model\FooterNavigation;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;

/** „Seiten“ → „Footer“ in der Verwaltung: aktuelle Footer-Seiten und alle Seiten zur Auswahl. */
readonly class GetFooterNavigationQuery
{
    public function __construct(
        private FooterNavigationManagerInterface $manager,
        private PageManagerInterface $pageManager,
    ) {
    }

    public function execute(): FooterNavigationResponse
    {
        $pages = $this->pageManager->all();
        usort($pages, static fn (Page $left, Page $right): int => strcasecmp($left->navigationLabel, $right->navigationLabel));
        $onlineIds = [];
        foreach ($this->pageManager->publishedPages() as $published) {
            if ($published->visible) {
                $onlineIds[$published->id] = true;
            }
        }
        $toResponse = static fn (Page $page): FooterPageResponse => new FooterPageResponse(
            $page->id,
            $page->navigationLabel,
            $page->slug,
            isset($onlineIds[$page->id]),
        );
        $navigation = $this->manager->get() ?? FooterNavigation::defaults($pages);

        return new FooterNavigationResponse(
            pages: array_map($toResponse, $navigation->pagesIn($pages)),
            available: array_map($toResponse, $pages),
        );
    }
}
