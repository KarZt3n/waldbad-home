<?php

namespace App\Tests\Unit\Logic\Media\PhotoAlbum\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumActionInput;
use App\Logic\Media\PhotoAlbum\Dto\SavePhotoAlbumRequest;
use App\Logic\Media\PhotoAlbum\Manager\PhotoAlbumManagerInterface;
use App\Logic\Media\PhotoAlbum\Mapping\PhotoAlbumModelFactory;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\UseCase\ImportPhotoAlbumsUseCase;
use App\Logic\Media\PhotoAlbum\UseCase\SavePhotoAlbumUseCase;
use PHPUnit\Framework\TestCase;

final class ImportPhotoAlbumsUseCaseTest extends TestCase
{
    public function testSkipsEntriesWithSameTitleAndDate(): void
    {
        $now = new \DateTimeImmutable('2026-09-25T10:00:00');
        $existing = new PhotoAlbum('existing', 'Flohmarkt am 26.04.2026', new \DateTimeImmutable('2026-04-26'), true, [], $now, $now);
        $manager = $this->createMock(PhotoAlbumManagerInterface::class);
        $manager->method('all')->willReturn([$existing]);
        $manager->expects(self::exactly(2))->method('save')->willReturnArgument(0);
        $identifiers = $this->createStub(IdentifierGeneratorInterface::class);
        $identifiers->method('generate')->willReturnCallback(static fn (): string => bin2hex(random_bytes(8)));
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);
        $useCase = new ImportPhotoAlbumsUseCase(
            $manager,
            new SavePhotoAlbumUseCase($manager, new PhotoAlbumModelFactory($identifiers), $identifiers, $clock),
        );
        $open = [new PhotoAlbumActionInput('Öffnen', 'https://photos.google.com/share/a', null)];

        $result = $useCase->execute([
            new SavePhotoAlbumRequest(null, ' flohmarkt am 26.04.2026 ', new \DateTimeImmutable('2026-04-26'), true, $open),
            new SavePhotoAlbumRequest(null, '19. Eisbaden am 03.01.2026', new \DateTimeImmutable('2026-01-03'), true, $open),
            new SavePhotoAlbumRequest(null, '....und dann kam Corona...', new \DateTimeImmutable('2020-03-15'), true, []),
            new SavePhotoAlbumRequest(null, '19. Eisbaden am 03.01.2026', new \DateTimeImmutable('2026-01-03'), true, $open),
        ]);

        self::assertSame(2, $result->created);
        self::assertSame(2, $result->skipped);
    }
}
