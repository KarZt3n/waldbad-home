<?php

namespace App\Logic\Media\Image\Model;

readonly class StoredImage
{
    public function __construct(
        public string $url,
        public string $originalName,
        public string $mimeType,
        public int $size,
        public int $width,
        public int $height,
    ) {
    }
}
