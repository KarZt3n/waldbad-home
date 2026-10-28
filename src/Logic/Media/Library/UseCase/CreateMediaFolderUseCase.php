<?php

namespace App\Logic\Media\Library\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Media\Library\Dto\CreateMediaFolderRequest;
use App\Logic\Media\Library\Exception\MediaFolderNotFoundException;
use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\Model\MediaFolder;

readonly class CreateMediaFolderUseCase
{
    public function __construct(private MediaLibraryStorageInterface $storage)
    {
    }

    public function execute(CreateMediaFolderRequest $request): MediaFolder
    {
        if (!$this->storage->folderExists($request->parentPath)) {
            throw new MediaFolderNotFoundException($request->parentPath);
        }
        $path = MediaFolder::childPath($request->parentPath, $request->name);
        if ($this->storage->folderExists($path)) {
            throw new BusinessRuleViolationException(sprintf('Der Ordner „%s“ existiert bereits.', $request->name));
        }

        return $this->storage->createFolder($path);
    }
}
