<?php

namespace App\Logic\Media\Image\Model;

readonly class ImageDimensions
{
    public function __construct(
        public int $width,
        public int $height,
    ) {
    }
}
