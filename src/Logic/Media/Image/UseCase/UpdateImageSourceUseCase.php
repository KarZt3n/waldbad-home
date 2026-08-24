<?php

namespace App\Logic\Media\Image\UseCase;

use App\Logic\Media\Image\ImageStorageInterface;
use App\Logic\Media\Image\Model\StoredImage;

readonly class UpdateImageSourceUseCase
{
    public function __construct(private ImageStorageInterface $storage)
    {
    }

    public function execute(string $url, ?string $source): StoredImage
    {
        return $this->storage->updateSource($url, $source);
    }
}
