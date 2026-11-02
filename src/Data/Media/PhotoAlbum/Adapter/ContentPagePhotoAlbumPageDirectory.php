<?php

namespace App\Data\Media\PhotoAlbum\Adapter;

use App\Logic\Content\Page\Exception\PageNotFoundException;
use App\Logic\Content\Page\Query\GetPublishedPageByIdQuery;
use App\Logic\Media\PhotoAlbum\PhotoAlbumPageDirectoryInterface;

/** Adapter auf die veröffentlichten Seiten des Seitenmoduls. */
readonly class ContentPagePhotoAlbumPageDirectory implements PhotoAlbumPageDirectoryInterface
{
    public function __construct(private GetPublishedPageByIdQuery $query)
    {
    }

    public function publishedSlug(string $pageId): ?string
    {
        try {
            return $this->query->execute($pageId)->slug;
        } catch (PageNotFoundException) {
            return null;
        }
    }
}
