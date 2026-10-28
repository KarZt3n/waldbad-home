<?php

namespace App\Logic\Media\Library\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Media\Library\Dto\ReplaceMediaFileRequest;
use App\Logic\Media\Library\Dto\ReplaceMediaFileResponse;
use App\Logic\Media\Library\Exception\MediaFileNotFoundException;
use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\MediaUsageSourceInterface;

/**
 * Bilder erhalten eine neue URL im selben Ordner (passende Dateiendung, kein veralteter
 * Browser-Cache); alle Verweise werden umgestellt, danach wird das alte Bild gelöscht. Andere
 * Dateien werden unter derselben URL überschrieben, weil sie meist aus Texten verlinkt sind,
 * deren Verweise die Mediathek nicht kennt.
 */
readonly class ReplaceMediaFileUseCase
{
    public function __construct(
        private MediaLibraryStorageInterface $storage,
        private MediaUsageSourceInterface $usageSource,
    ) {
    }

    public function execute(ReplaceMediaFileRequest $request): ReplaceMediaFileResponse
    {
        $current = $this->storage->find($request->url) ?? throw new MediaFileNotFoundException($request->url);
        $upload = $request->upload->withSource($request->upload->source ?? $current->source);

        if (!$current->isImage()) {
            return new ReplaceMediaFileResponse(file: $this->storage->overwrite($current->url, $upload), updatedReferences: 0);
        }

        $replacement = $this->storage->store($current->folderPath, $upload);
        if (!$replacement->isImage()) {
            $this->storage->delete($replacement->url);

            throw new BusinessRuleViolationException('Ein Bild kann nur durch ein anderes Bild ersetzt werden.');
        }
        $updatedReferences = $this->usageSource->replaceReferences($current->url, $replacement->url);
        $this->storage->delete($current->url);

        return new ReplaceMediaFileResponse(file: $replacement, updatedReferences: $updatedReferences);
    }
}
