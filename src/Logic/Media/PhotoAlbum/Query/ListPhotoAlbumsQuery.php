<?php

namespace App\Logic\Media\PhotoAlbum\Query;

use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumResponse;
use App\Logic\Media\PhotoAlbum\Manager\PhotoAlbumManagerInterface;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;

readonly class ListPhotoAlbumsQuery
{
    public function __construct(private PhotoAlbumManagerInterface $manager)
    {
    }

    /**
     * @return list<PhotoAlbumResponse> neueste zuerst; mit `$onlyVisible` nur im Frontend sichtbare
     */
    public function execute(bool $onlyVisible = false): array
    {
        $albums = $this->manager->all();
        if ($onlyVisible) {
            $albums = array_values(array_filter($albums, static fn (PhotoAlbum $album): bool => $album->visible));
        }

        return array_map(PhotoAlbumResponse::fromAlbum(...), $albums);
    }
}
