<?php

namespace App\Logic\Media\PhotoAlbum\Manager;

use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;

interface PhotoAlbumManagerInterface
{
    public function get(string $id): PhotoAlbum;

    /** @return list<PhotoAlbum> */
    public function all(): array;

    public function save(PhotoAlbum $album): PhotoAlbum;

    public function delete(string $id): void;
}
