<?php

namespace App\Logic\Media\Library\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Media\Library\Exception\MediaFolderNotFoundException;
use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\Model\MediaFolder;

readonly class DeleteMediaFolderUseCase
{
    public function __construct(private MediaLibraryStorageInterface $storage)
    {
    }

    public function execute(string $path): void
    {
        if ($path === '' || !$this->storage->folderExists($path)) {
            throw new MediaFolderNotFoundException($path);
        }
        if ($path === MediaFolder::DEFAULT_UPLOAD_FOLDER) {
            throw new BusinessRuleViolationException('Der Ordner „media“ nimmt die Uploads aus dem Seiteneditor auf und kann nicht gelöscht werden.');
        }
        foreach ($this->storage->folders() as $folder) {
            if (str_starts_with($folder->path, $path.'/')) {
                throw new BusinessRuleViolationException('Der Ordner enthält Unterordner und kann nicht gelöscht werden.');
            }
        }
        if ($this->storage->files($path, false) !== []) {
            throw new BusinessRuleViolationException('Der Ordner enthält noch Dateien und kann nicht gelöscht werden.');
        }

        $this->storage->deleteFolder($path);
    }
}
