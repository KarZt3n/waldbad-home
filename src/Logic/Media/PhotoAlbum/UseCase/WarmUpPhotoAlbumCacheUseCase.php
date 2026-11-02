<?php

namespace App\Logic\Media\PhotoAlbum\UseCase;

use App\Logic\Media\PhotoAlbum\Manager\PhotoAlbumManagerInterface;

readonly class WarmUpPhotoAlbumCacheUseCase
{
    public function __construct(private PhotoAlbumManagerInterface $manager)
    {
    }

    public function execute(): void
    {
        $this->manager->warmUpCache();
    }
}
