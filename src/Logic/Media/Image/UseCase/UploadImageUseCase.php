<?php

namespace App\Logic\Media\Image\UseCase;

use App\Logic\Media\Image\ImageStorageInterface;
use App\Logic\Media\Image\Model\ImageUpload;
use App\Logic\Media\Image\Model\StoredImage;

readonly class UploadImageUseCase
{
    public function __construct(private ImageStorageInterface $storage)
    {
    }

    public function execute(ImageUpload $upload): StoredImage
    {
        return $this->storage->store($upload);
    }
}
