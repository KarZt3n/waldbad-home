<?php

namespace App\Logic\Media\PhotoAlbum\Query;

use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumYearResponse;
use App\Logic\Media\PhotoAlbum\Service\PublicPhotoAlbumArchive;

readonly class ListPhotoAlbumYearsQuery
{
    public function __construct(private PublicPhotoAlbumArchive $archive)
    {
    }

    /**
     * @return list<PhotoAlbumYearResponse> neueste zuerst
     */
    public function execute(): array
    {
        return $this->archive->years();
    }
}
