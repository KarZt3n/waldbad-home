<?php

namespace App\UI\Media\Image\Http;

use App\Logic\Media\Image\Model\StoredImage;

final class StoredImageResponseFactory
{
    /**
     * @return array{url: string, originalName: string, mimeType: string, size: int, width: int, height: int, source: string|null}
     */
    public function image(StoredImage $image): array
    {
        return [
            'url' => $image->url,
            'originalName' => $image->originalName,
            'mimeType' => $image->mimeType,
            'size' => $image->size,
            'width' => $image->width,
            'height' => $image->height,
            'source' => $image->source,
        ];
    }
}
