<?php

namespace App\Logic\Media\PhotoAlbum\Dto;

readonly class PublicPhotoAlbumResponse
{
    /**
     * @param list<PublicPhotoAlbumActionResponse> $actions
     */
    public function __construct(
        public string $id,
        public string $title,
        public \DateTimeImmutable $date,
        public array $actions,
    ) {
    }
}
