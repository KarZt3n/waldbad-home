<?php

namespace App\Logic\Content\Page\Query;

use App\Logic\Content\Page\Dto\SitemapPageResponse;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\Page;

/** Alle veröffentlichten, sichtbaren Seiten für `sitemap.xml`, die Startseite zuerst. */
readonly class ListSitemapPagesQuery
{
    public function __construct(private PageManagerInterface $manager)
    {
    }

    /** @return list<SitemapPageResponse> */
    public function execute(): array
    {
        $pages = array_values(array_filter($this->manager->publishedPages(), static fn (Page $page): bool => $page->visible));
        usort($pages, static fn (Page $left, Page $right): int => [$left->slug !== 'startseite', $left->slug] <=> [$right->slug !== 'startseite', $right->slug]);

        return array_map(static fn (Page $page): SitemapPageResponse => new SitemapPageResponse(
            slug: $page->slug,
            lastModified: $page->publishedAt ?? $page->updatedAt,
        ), $pages);
    }
}
