<?php

namespace App\Tests\Unit\Logic\Media\Library\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Media\Library\Dto\CreateMediaFolderRequest;
use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\Model\MediaFolder;
use App\Logic\Media\Library\UseCase\CreateMediaFolderUseCase;
use App\Logic\Media\Library\UseCase\DeleteMediaFolderUseCase;
use PHPUnit\Framework\TestCase;

final class MediaFolderUseCasesTest extends TestCase
{
    public function testCreatesNestedFolder(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('folderExists')->willReturnMap([['dokumente', true], ['dokumente/2026', false]]);
        $storage->expects(self::once())->method('createFolder')->with('dokumente/2026')->willReturn(new MediaFolder('dokumente/2026', 0));

        $folder = (new CreateMediaFolderUseCase($storage))->execute(new CreateMediaFolderRequest('dokumente', '2026'));

        self::assertSame('2026', $folder->name());
        self::assertSame('dokumente', $folder->parentPath());
    }

    public function testRejectsFolderNamesThatAreNotUrlSafe(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('folderExists')->willReturn(true);
        $storage->expects(self::never())->method('createFolder');

        $this->expectException(BusinessRuleViolationException::class);
        (new CreateMediaFolderUseCase($storage))->execute(new CreateMediaFolderRequest('', 'Meine Dokumente'));
    }

    public function testDefaultUploadFolderCannotBeDeleted(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('folderExists')->willReturn(true);
        $storage->expects(self::never())->method('deleteFolder');

        $this->expectException(BusinessRuleViolationException::class);
        (new DeleteMediaFolderUseCase($storage))->execute('media');
    }

    public function testFoldersWithSubfoldersCannotBeDeleted(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('folderExists')->willReturn(true);
        $storage->method('folders')->willReturn([new MediaFolder('dokumente', 0), new MediaFolder('dokumente/2026', 1)]);
        $storage->expects(self::never())->method('deleteFolder');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Unterordner');
        (new DeleteMediaFolderUseCase($storage))->execute('dokumente');
    }

    public function testDeletesEmptyFolder(): void
    {
        $storage = $this->createMock(MediaLibraryStorageInterface::class);
        $storage->method('folderExists')->willReturn(true);
        $storage->method('folders')->willReturn([new MediaFolder('dokumente', 0)]);
        $storage->method('files')->willReturn([]);
        $storage->expects(self::once())->method('deleteFolder')->with('dokumente');

        (new DeleteMediaFolderUseCase($storage))->execute('dokumente');
    }
}
