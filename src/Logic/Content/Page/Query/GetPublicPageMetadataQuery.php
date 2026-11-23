<?php

namespace App\Logic\Content\Page\Query;

use App\Logic\Content\Page\Dto\PublicPageMetadataResponse;
use App\Logic\Content\Page\Exception\PageNotFoundException;
use App\Logic\Content\Page\Manager\PageManagerInterface;

/**
 * Metadaten der veröffentlichten, sichtbaren Seite — `null`, wenn es unter dem Slug keine gibt, damit
 * die Website dann mit Status 404 statt mit einer leeren 200-Seite antwortet.
 */
readonly class GetPublicPageMetadataQuery
{
    public function __construct(private PageManagerInterface $manager)
    {
    }

    public function execute(string $slug): ?PublicPageMetadataResponse
    {
        try {
            $page = $this->manager->getPublished($slug);
        } catch (PageNotFoundException) {
            return null;
        }
        $seoTitle = trim($page->seoTitle ?? '');
        $description = trim($page->seoDescription ?? '');

        return new PublicPageMetadataResponse(
            title: $seoTitle !== '' ? $seoTitle : $page->title,
            description: $description !== '' ? $description : null,
        );
    }
}
