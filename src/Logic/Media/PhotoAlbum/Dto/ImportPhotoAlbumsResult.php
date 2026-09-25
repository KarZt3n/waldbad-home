<?php

namespace App\Logic\Media\PhotoAlbum\Dto;

readonly class ImportPhotoAlbumsResult
{
    public function __construct(
        public int $created,
        public int $skipped,
    ) {
    }
}
