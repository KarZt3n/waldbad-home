<?php

namespace App\Logic\Media\PhotoAlbum\Dto;

readonly class PhotoAlbumYearResponse
{
    /**
     * @param list<PublicPhotoAlbumResponse> $albums neueste zuerst
     */
    public function __construct(
        public int $year,
        public array $albums,
    ) {
    }
}
