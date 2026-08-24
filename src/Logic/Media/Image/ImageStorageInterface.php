<?php

namespace App\Logic\Media\Image;

use App\Logic\Media\Image\Model\ImageUpload;
use App\Logic\Media\Image\Model\StoredImage;

interface ImageStorageInterface
{
    public function store(ImageUpload $upload): StoredImage;

    /**
     * @return list<StoredImage>
     */
    public function all(): array;

    public function updateSource(string $url, ?string $source): StoredImage;
}
