<?php

namespace App\Logic\Media\PhotoAlbum\Manager;

use App\Logic\Media\PhotoAlbum\Exception\PhotoAlbumNotFoundException;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\PhotoAlbumCacheInterface;
use App\Logic\Media\PhotoAlbum\PhotoAlbumProcessorInterface;
use App\Logic\Media\PhotoAlbum\PhotoAlbumProviderInterface;

/**
 * Die Liste aller Einträge wird serverseitig gecacht und nach jedem Schreiben sofort neu aufgebaut
 * (Warm-up), damit auch der erste Besucher danach nicht auf die Datenbank wartet.
 */
readonly class PhotoAlbumManager implements PhotoAlbumManagerInterface
{
    public function __construct(
        private PhotoAlbumProviderInterface $provider,
        private PhotoAlbumProcessorInterface $processor,
        private PhotoAlbumCacheInterface $cache,
    ) {
    }

    public function get(string $id): PhotoAlbum
    {
        return $this->provider->find($id) ?? throw new PhotoAlbumNotFoundException($id);
    }

    public function all(): array
    {
        return $this->cache->load() ?? $this->rebuildCache();
    }

    public function save(PhotoAlbum $album): PhotoAlbum
    {
        $saved = $this->processor->save($album);
        $this->rebuildCache();

        return $saved;
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
        $this->rebuildCache();
    }

    public function warmUpCache(): void
    {
        $this->rebuildCache();
    }

    /**
     * @return list<PhotoAlbum>
     */
    private function rebuildCache(): array
    {
        $albums = $this->provider->findAll();
        $this->cache->store($albums);

        return $albums;
    }
}
