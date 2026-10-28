<?php

namespace App\Logic\Content\Page\Query;

use App\Logic\Content\Page\Dto\PageMediaReferenceResponse;
use App\Logic\Content\Page\Manager\PageManagerInterface;

/** Welche Seiten (Entwurf und/oder veröffentlichte Fassung) welche Bilder verwenden. */
readonly class ListPageMediaReferencesQuery
{
    public function __construct(private PageManagerInterface $manager)
    {
    }

    /**
     * @return list<PageMediaReferenceResponse>
     */
    public function execute(): array
    {
        $references = [];
        foreach ($this->manager->all() as $page) {
            foreach ($page->mediaUrls() as $url) {
                $references[$page->id."\0".$url] = new PageMediaReferenceResponse(
                    mediaUrl: $url,
                    pageId: $page->id,
                    pageTitle: $page->title,
                    inDraft: true,
                    inPublication: false,
                );
            }
        }
        foreach ($this->manager->publishedPages() as $page) {
            foreach ($page->mediaUrls() as $url) {
                $key = $page->id."\0".$url;
                $references[$key] = new PageMediaReferenceResponse(
                    mediaUrl: $url,
                    pageId: $page->id,
                    pageTitle: $references[$key]->pageTitle ?? $page->title,
                    inDraft: isset($references[$key]),
                    inPublication: true,
                );
            }
        }

        return array_values($references);
    }
}
