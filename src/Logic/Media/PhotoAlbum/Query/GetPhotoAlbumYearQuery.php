<?php

namespace App\Logic\Media\PhotoAlbum\Query;

use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumYearResponse;
use App\Logic\Media\PhotoAlbum\Service\PublicPhotoAlbumArchive;

readonly class GetPhotoAlbumYearQuery
{
    public function __construct(private PublicPhotoAlbumArchive $archive)
    {
    }

    public function execute(int $year): PhotoAlbumYearResponse
    {
        return $this->archive->get($year);
    }
}
