<?php

namespace App\Tests\Unit\Logic\Media\PhotoAlbum\Service;

use App\Logic\Media\PhotoAlbum\Manager\PhotoAlbumManagerInterface;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbumAction;
use App\Logic\Media\PhotoAlbum\PhotoAlbumPageDirectoryInterface;
use App\Logic\Media\PhotoAlbum\Service\PublicPhotoAlbumArchive;
use PHPUnit\Framework\TestCase;

final class PublicPhotoAlbumArchiveTest extends TestCase
{
    public function testGroupsVisibleAlbumsByYearNewestFirst(): void
    {
        $archive = $this->archive([
            $this->album('a', 'Eisbaden', '2026-01-03'),
            $this->album('b', 'Entwurf', '2025-08-01', visible: false),
            $this->album('c', 'Waldbadlauf', '2025-05-01'),
            $this->album('d', 'Flohmarkt', '2025-04-26'),
            $this->album('e', 'Nur Entwurf', '2024-04-26', visible: false),
        ]);

        $years = $archive->years();

        self::assertSame([2026, 2025], array_map(static fn ($year): int => $year->year, $years));
        self::assertSame(['c', 'd'], array_map(static fn ($album): string => $album->id, $years[1]->albums));
        self::assertSame(['c', 'd'], array_map(static fn ($album): string => $album->id, $archive->get(2025)->albums));
    }

    public function testResolvesPageSlugsAndReturnsEmptyYear(): void
    {
        $archive = $this->archive([
            $this->album('a', 'Eisbaden', '2025-01-03', actions: [
                new PhotoAlbumAction('x', 0, 'Bericht', null, 'page-1'),
                new PhotoAlbumAction('y', 1, 'Alt', null, 'deleted-page'),
                new PhotoAlbumAction('z', 2, 'Öffnen', 'https://photos.google.com/share/a', null),
            ]),
        ]);

        $actions = $archive->get(2025)->albums[0]->actions;

        self::assertSame(['bericht', null, null], array_map(static fn ($action): ?string => $action->pageSlug, $actions));
        self::assertSame([], $archive->get(2019)->albums);
    }

    /**
     * @param list<PhotoAlbum> $albums
     */
    private function archive(array $albums): PublicPhotoAlbumArchive
    {
        $manager = $this->createStub(PhotoAlbumManagerInterface::class);
        $manager->method('all')->willReturn($albums);
        $pages = $this->createStub(PhotoAlbumPageDirectoryInterface::class);
        $pages->method('publishedSlug')->willReturnCallback(static fn (string $pageId): ?string => $pageId === 'page-1' ? 'bericht' : null);

        return new PublicPhotoAlbumArchive($manager, $pages);
    }

    /**
     * @param list<PhotoAlbumAction> $actions
     */
    private function album(string $id, string $title, string $date, bool $visible = true, array $actions = []): PhotoAlbum
    {
        $createdAt = new \DateTimeImmutable('2026-09-25T10:00:00');

        return new PhotoAlbum($id, $title, new \DateTimeImmutable($date), $visible, $actions, $createdAt, $createdAt);
    }
}
