<?php

namespace App\Logic\Media\PhotoAlbum;

use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;

interface PhotoAlbumProcessorInterface
{
    public function save(PhotoAlbum $album): PhotoAlbum;

    public function delete(string $id): void;
}
