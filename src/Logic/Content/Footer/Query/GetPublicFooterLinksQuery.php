<?php

namespace App\Logic\Content\Footer\Query;

use App\Logic\Content\Footer\Dto\FooterLinkResponse;
use App\Logic\Content\Footer\Manager\FooterNavigationManagerInterface;
use App\Logic\Content\Footer\Model\FooterNavigation;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;

/** Footer-Links der Website: nur veröffentlichte, sichtbare Seiten, beschriftet mit ihrer Navigationsbezeichnung. */
readonly class GetPublicFooterLinksQuery
{
    public function __construct(
        private FooterNavigationManagerInterface $manager,
        private PageManagerInterface $pageManager,
    ) {
    }

    /** @return list<FooterLinkResponse> */
    public function execute(): array
    {
        $pages = array_values(array_filter($this->pageManager->publishedPages(), static fn (Page $page): bool => $page->visible));
        $navigation = $this->manager->get() ?? FooterNavigation::defaults($pages);

        return array_map(
            static fn (Page $page): FooterLinkResponse => new FooterLinkResponse($page->navigationLabel, $page->slug),
            $navigation->pagesIn($pages),
        );
    }
}
