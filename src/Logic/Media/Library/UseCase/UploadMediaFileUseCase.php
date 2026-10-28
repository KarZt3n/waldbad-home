<?php

namespace App\Logic\Media\Library\UseCase;

use App\Logic\Media\Library\Dto\UploadMediaFileRequest;
use App\Logic\Media\Library\Exception\MediaFolderNotFoundException;
use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\Model\MediaFile;

readonly class UploadMediaFileUseCase
{
    public function __construct(private MediaLibraryStorageInterface $storage)
    {
    }

    public function execute(UploadMediaFileRequest $request): MediaFile
    {
        if (!$this->storage->folderExists($request->folderPath)) {
            throw new MediaFolderNotFoundException($request->folderPath);
        }

        return $this->storage->store($request->folderPath, $request->upload);
    }
}
