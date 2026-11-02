<?php

namespace App\Logic\Media\PhotoAlbum\Dto;

readonly class PublicPhotoAlbumActionResponse
{
    /**
     * @param string|null $pageSlug Slug der verlinkten Seite; `null` bei URL-Buttons und nicht veröffentlichten Seiten
     */
    public function __construct(
        public string $label,
        public ?string $url,
        public ?string $pageId,
        public ?string $pageSlug,
    ) {
    }
}
