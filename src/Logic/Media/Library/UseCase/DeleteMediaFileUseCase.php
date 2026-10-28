<?php

namespace App\Logic\Media\Library\UseCase;

use App\Logic\Media\Library\Dto\DeleteMediaFileResponse;
use App\Logic\Media\Library\Exception\MediaFileNotFoundException;
use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\MediaUsageSourceInterface;

readonly class DeleteMediaFileUseCase
{
    public function __construct(
        private MediaLibraryStorageInterface $storage,
        private MediaUsageSourceInterface $usageSource,
    ) {
    }

    public function execute(string $url): DeleteMediaFileResponse
    {
        if ($this->storage->find($url) === null) {
            throw new MediaFileNotFoundException($url);
        }

        // Verweise zuerst entfernen: Scheitert das, bleibt die Datei erhalten und nichts zeigt ins Leere.
        $removedReferences = $this->usageSource->replaceReferences($url, null);
        $this->storage->delete($url);

        return new DeleteMediaFileResponse(removedReferences: $removedReferences);
    }
}
