<?php

namespace App\Logic\Media\PhotoAlbum\UseCase;

use App\Logic\Media\PhotoAlbum\Manager\PhotoAlbumManagerInterface;

readonly class DeletePhotoAlbumUseCase
{
    public function __construct(private PhotoAlbumManagerInterface $manager)
    {
    }

    public function execute(string $id): void
    {
        $this->manager->get($id);
        $this->manager->delete($id);
    }
}
