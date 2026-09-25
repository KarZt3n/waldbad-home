<?php

namespace App\Logic\Media\PhotoAlbum\Dto;

readonly class PhotoAlbumActionInput
{
    public function __construct(
        public string $label,
        public ?string $url,
        public ?string $pageId,
    ) {
    }
}
