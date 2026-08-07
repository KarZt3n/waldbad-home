<?php

namespace App\Logic\Media\Image\Query;

use App\Logic\Media\Image\ImageStorageInterface;
use App\Logic\Media\Image\Model\StoredImage;

readonly class ListStoredImagesQuery
{
    public function __construct(private ImageStorageInterface $storage)
    {
    }

    /**
     * @return list<StoredImage>
     */
    public function execute(): array
    {
        return $this->storage->all();
    }
}
