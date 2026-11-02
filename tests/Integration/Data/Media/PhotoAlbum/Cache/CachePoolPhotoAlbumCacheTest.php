<?php

namespace App\Tests\Integration\Data\Media\PhotoAlbum\Cache;

use App\Data\Media\PhotoAlbum\Cache\CachePoolPhotoAlbumCache;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbumAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;

final class CachePoolPhotoAlbumCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/photo-album-cache-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testStoredAlbumsSurviveANewPoolInstance(): void
    {
        $now = new \DateTimeImmutable('2026-09-25T10:00:00');
        $albums = [new PhotoAlbum('a', 'Eisbaden', new \DateTimeImmutable('2025-01-03'), true, [
            new PhotoAlbumAction('x', 0, 'Öffnen', 'https://photos.google.com/share/a', null),
        ], $now, $now)];

        self::assertNull($this->cache()->load());
        $this->cache()->store($albums);

        self::assertEquals($albums, $this->cache()->load());
    }

    public function testUnexpectedContentIsTreatedAsMiss(): void
    {
        $pool = new FilesystemAdapter('photo_albums', 0, $this->directory);
        $pool->save($pool->getItem('photo_albums.all')->set(['kein Album']));

        self::assertNull($this->cache()->load());
    }

    private function cache(): CachePoolPhotoAlbumCache
    {
        return new CachePoolPhotoAlbumCache(new FilesystemAdapter('photo_albums', 0, $this->directory));
    }
}
