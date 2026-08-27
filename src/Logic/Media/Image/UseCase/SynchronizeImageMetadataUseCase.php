<?php

namespace App\Logic\Media\Image\UseCase;

use App\Logic\Media\Image\ImageStorageInterface;

readonly class SynchronizeImageMetadataUseCase
{
    public function __construct(private ImageStorageInterface $storage)
    {
    }

    public function execute(): int
    {
        return $this->storage->synchronizeMetadata();
    }
}
