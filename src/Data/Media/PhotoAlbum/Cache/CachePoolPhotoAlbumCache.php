<?php

namespace App\Data\Media\PhotoAlbum\Cache;

use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\PhotoAlbumCacheInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class CachePoolPhotoAlbumCache implements PhotoAlbumCacheInterface
{
    private const string KEY = 'photo_albums.all';

    public function __construct(#[Autowire(service: 'cache.photo_albums')] private CacheItemPoolInterface $pool)
    {
    }

    public function load(): ?array
    {
        $item = $this->pool->getItem(self::KEY);
        $cached = $item->isHit() ? $item->get() : null;
        if (!is_array($cached) || !array_is_list($cached)) {
            return null;
        }
        $albums = [];
        foreach ($cached as $album) {
            // Unerwarteter Inhalt (z. B. aus einer älteren Modellfassung) gilt als Cache-Miss statt als Fehler.
            if (!$album instanceof PhotoAlbum) {
                return null;
            }
            $albums[] = $album;
        }

        return $albums;
    }

    public function store(array $albums): void
    {
        $this->pool->save($this->pool->getItem(self::KEY)->set($albums));
    }
}
