<?php

namespace App\Logic\Media\PhotoAlbum;

use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;

interface PhotoAlbumProviderInterface
{
    public function find(string $id): ?PhotoAlbum;

    /** @return list<PhotoAlbum> nach Datum absteigend (neueste zuerst) */
    public function findAll(): array;
}
