<?php

namespace App\Tests\Unit\Logic\Media\PhotoAlbum\Manager;

use App\Logic\Media\PhotoAlbum\Manager\PhotoAlbumManager;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\PhotoAlbumCacheInterface;
use App\Logic\Media\PhotoAlbum\PhotoAlbumProcessorInterface;
use App\Logic\Media\PhotoAlbum\PhotoAlbumProviderInterface;
use PHPUnit\Framework\TestCase;

final class PhotoAlbumManagerTest extends TestCase
{
    public function testAllComesFromCacheWithoutDatabase(): void
    {
        $cached = [$this->album('a')];
        $provider = $this->createMock(PhotoAlbumProviderInterface::class);
        $provider->expects(self::never())->method('findAll');
        $cache = $this->createStub(PhotoAlbumCacheInterface::class);
        $cache->method('load')->willReturn($cached);

        self::assertSame($cached, $this->manager($provider, $cache)->all());
    }

    public function testCacheMissLoadsFromDatabaseAndFillsCache(): void
    {
        $albums = [$this->album('a')];
        $provider = $this->createStub(PhotoAlbumProviderInterface::class);
        $provider->method('findAll')->willReturn($albums);
        $cache = $this->createMock(PhotoAlbumCacheInterface::class);
        $cache->method('load')->willReturn(null);
        $cache->expects(self::once())->method('store')->with($albums);

        self::assertSame($albums, $this->manager($provider, $cache)->all());
    }

    public function testSaveAndDeleteRebuildCacheAfterWriting(): void
    {
        $album = $this->album('a');
        $calls = [];
        $provider = $this->createStub(PhotoAlbumProviderInterface::class);
        $provider->method('findAll')->willReturnCallback(static function () use (&$calls, $album): array {
            $calls[] = 'findAll';

            return [$album];
        });
        $processor = $this->createStub(PhotoAlbumProcessorInterface::class);
        $processor->method('save')->willReturnCallback(static function (PhotoAlbum $saved) use (&$calls): PhotoAlbum {
            $calls[] = 'save';

            return $saved;
        });
        $processor->method('delete')->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'delete';
        });
        $cache = $this->createStub(PhotoAlbumCacheInterface::class);
        $cache->method('store')->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'store';
        });
        $manager = new PhotoAlbumManager($provider, $processor, $cache);

        $manager->save($album);
        $manager->delete('a');
        $manager->warmUpCache();

        self::assertSame(['save', 'findAll', 'store', 'delete', 'findAll', 'store', 'findAll', 'store'], $calls);
    }

    private function manager(PhotoAlbumProviderInterface $provider, PhotoAlbumCacheInterface $cache): PhotoAlbumManager
    {
        return new PhotoAlbumManager($provider, $this->createStub(PhotoAlbumProcessorInterface::class), $cache);
    }

    private function album(string $id): PhotoAlbum
    {
        $now = new \DateTimeImmutable('2026-09-25T10:00:00');

        return new PhotoAlbum($id, 'Eisbaden', new \DateTimeImmutable('2025-01-03'), true, [], $now, $now);
    }
}
