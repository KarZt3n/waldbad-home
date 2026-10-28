<?php

namespace App\Logic\Media\Library\UseCase;

use App\Logic\Media\Library\Dto\MoveMediaFileRequest;
use App\Logic\Media\Library\Dto\MoveMediaFileResponse;
use App\Logic\Media\Library\Exception\MediaFileNotFoundException;
use App\Logic\Media\Library\Exception\MediaFolderNotFoundException;
use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\MediaUsageSourceInterface;

/** Verschiebt eine Datei in einen anderen Ordner und stellt alle bekannten Verweise auf die neue URL um. */
readonly class MoveMediaFileUseCase
{
    public function __construct(
        private MediaLibraryStorageInterface $storage,
        private MediaUsageSourceInterface $usageSource,
    ) {
    }

    public function execute(MoveMediaFileRequest $request): MoveMediaFileResponse
    {
        $current = $this->storage->find($request->url) ?? throw new MediaFileNotFoundException($request->url);
        if (!$this->storage->folderExists($request->targetFolderPath)) {
            throw new MediaFolderNotFoundException($request->targetFolderPath);
        }
        if ($current->folderPath === $request->targetFolderPath) {
            return new MoveMediaFileResponse(file: $current, updatedReferences: 0);
        }

        $moved = $this->storage->move($current->url, $request->targetFolderPath);
        try {
            $updatedReferences = $this->usageSource->replaceReferences($current->url, $moved->url);
        } catch (\Throwable $exception) {
            // Teilweise umgestellte Verweise zurücksetzen und die Datei zurückholen, damit nichts ins Leere zeigt.
            $this->usageSource->replaceReferences($moved->url, $current->url);
            $this->storage->move($moved->url, $current->folderPath);

            throw $exception;
        }

        return new MoveMediaFileResponse(file: $moved, updatedReferences: $updatedReferences);
    }
}
