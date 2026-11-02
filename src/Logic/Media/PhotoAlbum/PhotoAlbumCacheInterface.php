<?php

namespace App\Logic\Media\PhotoAlbum;

use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;

interface PhotoAlbumCacheInterface
{
    /** @return list<PhotoAlbum>|null `null`, wenn (noch) nichts gecacht ist */
    public function load(): ?array;

    /** @param list<PhotoAlbum> $albums */
    public function store(array $albums): void;
}
