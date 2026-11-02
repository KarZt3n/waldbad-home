<?php

namespace App\Logic\Media\PhotoAlbum\Query;

use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumResponse;
use App\Logic\Media\PhotoAlbum\Manager\PhotoAlbumManagerInterface;

readonly class ListPhotoAlbumsQuery
{
    public function __construct(private PhotoAlbumManagerInterface $manager)
    {
    }

    /**
     * @return list<PhotoAlbumResponse> neueste zuerst
     */
    public function execute(): array
    {
        return array_map(PhotoAlbumResponse::fromAlbum(...), $this->manager->all());
    }
}
