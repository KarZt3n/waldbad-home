<?php

namespace App\Logic\Media\Library\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Media\Library\Dto\RenameMediaFileRequest;
use App\Logic\Media\Library\Dto\RenameMediaFileResponse;
use App\Logic\Media\Library\Exception\MediaFileNotFoundException;
use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\MediaUsageSourceInterface;

/** Benennt eine Datei im selben Ordner um und stellt alle bekannten Verweise auf die neue URL um. */
readonly class RenameMediaFileUseCase
{
    private const int MAX_FILENAME_LENGTH = 180;

    public function __construct(
        private MediaLibraryStorageInterface $storage,
        private MediaUsageSourceInterface $usageSource,
    ) {
    }

    public function execute(RenameMediaFileRequest $request): RenameMediaFileResponse
    {
        $filename = trim($request->filename);
        if ($filename === '') {
            throw new BusinessRuleViolationException('Bitte geben Sie einen Dateinamen an.');
        }
        if (mb_strlen($filename) > self::MAX_FILENAME_LENGTH) {
            throw new BusinessRuleViolationException(sprintf('Der Dateiname darf höchstens %d Zeichen lang sein.', self::MAX_FILENAME_LENGTH));
        }
        $current = $this->storage->find($request->url) ?? throw new MediaFileNotFoundException($request->url);

        $renamed = $this->storage->rename($current->url, $filename);
        if ($renamed->url === $current->url) {
            return new RenameMediaFileResponse(file: $renamed, updatedReferences: 0);
        }
        try {
            $updatedReferences = $this->usageSource->replaceReferences($current->url, $renamed->url);
        } catch (\Throwable $exception) {
            // Teilweise umgestellte Verweise zurücksetzen und den alten Namen wiederherstellen, damit nichts ins Leere zeigt.
            $this->usageSource->replaceReferences($renamed->url, $current->url);
            $this->storage->rename($renamed->url, $current->filenameWithoutExtension());

            throw $exception;
        }

        return new RenameMediaFileResponse(file: $renamed, updatedReferences: $updatedReferences);
    }
}
