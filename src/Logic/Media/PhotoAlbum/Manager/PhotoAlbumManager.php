<?php

namespace App\Logic\Media\PhotoAlbum\Manager;

use App\Logic\Media\PhotoAlbum\Exception\PhotoAlbumNotFoundException;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\PhotoAlbumProcessorInterface;
use App\Logic\Media\PhotoAlbum\PhotoAlbumProviderInterface;

readonly class PhotoAlbumManager implements PhotoAlbumManagerInterface
{
    public function __construct(
        private PhotoAlbumProviderInterface $provider,
        private PhotoAlbumProcessorInterface $processor,
    ) {
    }

    public function get(string $id): PhotoAlbum
    {
        return $this->provider->find($id) ?? throw new PhotoAlbumNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function save(PhotoAlbum $album): PhotoAlbum
    {
        return $this->processor->save($album);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }
}
