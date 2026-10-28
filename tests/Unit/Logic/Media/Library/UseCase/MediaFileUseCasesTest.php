<?php

namespace App\Tests\Unit\Logic\Media\Library\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Media\Library\Dto\MoveMediaFileRequest;
use App\Logic\Media\Library\Dto\RenameMediaFileRequest;
use App\Logic\Media\Library\Dto\ReplaceMediaFileRequest;
use App\Logic\Media\Library\Exception\MediaFileNotFoundException;
use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\MediaUsageSourceInterface;
use App\Logic\Media\Library\Model\MediaFile;
use App\Logic\Media\Library\Model\MediaUpload;
use App\Logic\Media\Library\UseCase\DeleteMediaFileUseCase;
use App\Logic\Media\Library\UseCase\MoveMediaFileUseCase;
use App\Logic\Media\Library\UseCase\RenameMediaFileUseCase;
use App\Logic\Media\Library\UseCase\ReplaceMediaFileUseCase;
use PHPUnit\Framework\TestCase;

final class MediaFileUseCasesTest extends TestCase
{
    public function testDeleteRemovesReferencesBeforeTheFile(): void
    {
        $calls = [];
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('find')->willReturn($this->file('/uploads/media/alt.png'));
        $storage->expects(self::once())->method('delete')->with('/uploads/media/alt.png')
            ->willReturnCallback(static function () use (&$calls): void { $calls[] = 'delete'; });
        $usages = $this->createMock(MediaUsageSourceInterface::class);
        $usages->expects(self::once())->method('replaceReferences')->with('/uploads/media/alt.png', null)
            ->willReturnCallback(static function () use (&$calls): int {
                $calls[] = 'references';

                return 3;
            });

        self::assertSame(3, (new DeleteMediaFileUseCase($storage, $usages))->execute('/uploads/media/alt.png')->removedReferences);
        self::assertSame(['references', 'delete'], $calls);
    }

    public function testDeleteRejectsUnknownFiles(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('find')->willReturn(null);
        $storage->expects(self::never())->method('delete');
        $usages = $this->createMock(MediaUsageSourceInterface::class);
        $usages->expects(self::never())->method('replaceReferences');

        $this->expectException(MediaFileNotFoundException::class);
        (new DeleteMediaFileUseCase($storage, $usages))->execute('/uploads/media/fehlt.png');
    }

    public function testReplacingAnImageStoresItInTheSameFolderAndRedirectsReferences(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('find')->willReturn($this->file('/uploads/bilder/alt.png', source: 'Foto: Verein'));
        $storage->expects(self::once())->method('store')
            ->with('bilder', self::callback(static fn (MediaUpload $upload): bool => $upload->source === 'Foto: Verein'))
            ->willReturn($this->file('/uploads/bilder/neu.png'));
        $storage->expects(self::once())->method('delete')->with('/uploads/bilder/alt.png');
        $usages = $this->createMock(MediaUsageSourceInterface::class);
        $usages->expects(self::once())->method('replaceReferences')->with('/uploads/bilder/alt.png', '/uploads/bilder/neu.png')->willReturn(2);

        $response = (new ReplaceMediaFileUseCase($storage, $usages))->execute(new ReplaceMediaFileRequest('/uploads/bilder/alt.png', $this->upload()));

        self::assertSame('/uploads/bilder/neu.png', $response->file->url);
        self::assertSame(2, $response->updatedReferences);
    }

    public function testImagesCannotBeReplacedByDocuments(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('find')->willReturn($this->file('/uploads/bilder/alt.png'));
        $storage->method('store')->willReturn($this->file('/uploads/bilder/neu.pdf', 'application/pdf'));
        $storage->expects(self::once())->method('delete')->with('/uploads/bilder/neu.pdf');
        $usages = $this->createMock(MediaUsageSourceInterface::class);
        $usages->expects(self::never())->method('replaceReferences');

        $this->expectException(BusinessRuleViolationException::class);
        (new ReplaceMediaFileUseCase($storage, $usages))->execute(new ReplaceMediaFileRequest('/uploads/bilder/alt.png', $this->upload()));
    }

    public function testDocumentsAreOverwrittenUnderTheSameUrl(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('find')->willReturn($this->file('/uploads/dokumente/satzung.pdf', 'application/pdf'));
        $storage->expects(self::never())->method('store');
        $storage->expects(self::once())->method('overwrite')->with('/uploads/dokumente/satzung.pdf')
            ->willReturn($this->file('/uploads/dokumente/satzung.pdf', 'application/pdf'));
        $usages = $this->createMock(MediaUsageSourceInterface::class);
        $usages->expects(self::never())->method('replaceReferences');

        $response = (new ReplaceMediaFileUseCase($storage, $usages))->execute(new ReplaceMediaFileRequest('/uploads/dokumente/satzung.pdf', $this->upload()));

        self::assertSame('/uploads/dokumente/satzung.pdf', $response->file->url);
    }

    public function testMoveRedirectsReferencesToTheNewUrl(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('find')->willReturn($this->file('/uploads/media/teich.png'));
        $storage->method('folderExists')->willReturn(true);
        $storage->expects(self::once())->method('move')->with('/uploads/media/teich.png', 'bilder')
            ->willReturn($this->file('/uploads/bilder/teich.png'));
        $usages = $this->createMock(MediaUsageSourceInterface::class);
        $usages->expects(self::once())->method('replaceReferences')->with('/uploads/media/teich.png', '/uploads/bilder/teich.png')->willReturn(4);

        $response = (new MoveMediaFileUseCase($storage, $usages))->execute(new MoveMediaFileRequest('/uploads/media/teich.png', 'bilder'));

        self::assertSame('/uploads/bilder/teich.png', $response->file->url);
        self::assertSame(4, $response->updatedReferences);
    }

    public function testFailedReferenceUpdateMovesTheFileBack(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('find')->willReturn($this->file('/uploads/media/teich.png'));
        $storage->method('folderExists')->willReturn(true);
        $moves = [];
        $storage->expects(self::exactly(2))->method('move')->willReturnCallback(function (string $url, string $folder) use (&$moves): MediaFile {
            $moves[] = [$url, $folder];

            return $this->file($folder === 'bilder' ? '/uploads/bilder/teich.png' : '/uploads/media/teich.png');
        });
        $usages = $this->createMock(MediaUsageSourceInterface::class);
        $usages->expects(self::exactly(2))->method('replaceReferences')->willReturnCallback(static function (string $url): int {
            if ($url === '/uploads/media/teich.png') {
                throw new \RuntimeException('Seite gesperrt');
            }

            return 0;
        });

        try {
            (new MoveMediaFileUseCase($storage, $usages))->execute(new MoveMediaFileRequest('/uploads/media/teich.png', 'bilder'));
            self::fail('Die Exception wurde nicht weitergereicht.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Seite gesperrt', $exception->getMessage());
        }
        self::assertSame([['/uploads/media/teich.png', 'bilder'], ['/uploads/bilder/teich.png', 'media']], $moves);
    }

    public function testRenameRedirectsReferencesToTheNewUrl(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('find')->willReturn($this->file('/uploads/media/img-123.png'));
        $storage->expects(self::once())->method('rename')->with('/uploads/media/img-123.png', 'Teich im Sommer')
            ->willReturn($this->file('/uploads/media/teich-im-sommer.png'));
        $usages = $this->createMock(MediaUsageSourceInterface::class);
        $usages->expects(self::once())->method('replaceReferences')->with('/uploads/media/img-123.png', '/uploads/media/teich-im-sommer.png')->willReturn(2);

        $response = (new RenameMediaFileUseCase($storage, $usages))->execute(new RenameMediaFileRequest('/uploads/media/img-123.png', ' Teich im Sommer '));

        self::assertSame('/uploads/media/teich-im-sommer.png', $response->file->url);
        self::assertSame(2, $response->updatedReferences);
    }

    public function testRenameToTheSameNameLeavesReferencesUntouched(): void
    {
        $storage = $this->createStub(MediaLibraryStorageInterface::class);
        $storage->method('find')->willReturn($this->file('/uploads/media/teich.png'));
        $storage->method('rename')->willReturn($this->file('/uploads/media/teich.png'));
        $usages = $this->createMock(MediaUsageSourceInterface::class);
        $usages->expects(self::never())->method('replaceReferences');

        $response = (new RenameMediaFileUseCase($storage, $usages))->execute(new RenameMediaFileRequest('/uploads/media/teich.png', 'teich'));

        self::assertSame(0, $response->updatedReferences);
    }

    public function testRenameRequiresAFilename(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->expects(self::never())->method('rename');

        $this->expectException(BusinessRuleViolationException::class);
        (new RenameMediaFileUseCase($storage, $this->createStub(MediaUsageSourceInterface::class)))->execute(new RenameMediaFileRequest('/uploads/media/teich.png', '   '));
    }

    public function testFailedReferenceUpdateRestoresTheOldName(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('find')->willReturn($this->file('/uploads/media/teich.png'));
        $renames = [];
        $storage->expects(self::exactly(2))->method('rename')->willReturnCallback(function (string $url, string $filename) use (&$renames): MediaFile {
            $renames[] = [$url, $filename];

            return $this->file('/uploads/media/'.$filename.'.png');
        });
        $usages = $this->createMock(MediaUsageSourceInterface::class);
        $usages->expects(self::exactly(2))->method('replaceReferences')->willReturnCallback(static function (string $url): int {
            if ($url === '/uploads/media/teich.png') {
                throw new \RuntimeException('Seite gesperrt');
            }

            return 0;
        });

        try {
            (new RenameMediaFileUseCase($storage, $usages))->execute(new RenameMediaFileRequest('/uploads/media/teich.png', 'see'));
            self::fail('Die Exception wurde nicht weitergereicht.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Seite gesperrt', $exception->getMessage());
        }
        self::assertSame([['/uploads/media/teich.png', 'see'], ['/uploads/media/see.png', 'teich']], $renames);
    }

    private function upload(): MediaUpload
    {
        return new MediaUpload(temporaryPath: '/tmp/upload', originalName: 'neu', size: 100);
    }

    private function file(string $url, string $mimeType = 'image/png', ?string $source = null): MediaFile
    {
        $folder = dirname(substr($url, strlen('/uploads/')));

        return new MediaFile(
            url: $url,
            folderPath: $folder === '.' ? '' : $folder,
            originalName: basename($url),
            mimeType: $mimeType,
            size: 100,
            width: $mimeType === 'image/png' ? 1 : null,
            height: $mimeType === 'image/png' ? 1 : null,
            source: $source,
            modifiedAt: new \DateTimeImmutable('2026-10-28T12:00:00+01:00'),
        );
    }
}
