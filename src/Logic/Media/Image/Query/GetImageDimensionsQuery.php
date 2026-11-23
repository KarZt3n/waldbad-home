<?php

namespace App\Logic\Media\Image\Query;

use App\Logic\Media\Image\ImageStorageInterface;
use App\Logic\Media\Image\Model\ImageDimensions;

/** Pixelmaße von Bildern der Mediathek, damit Seiten den Platz für Bilder vor dem Laden reservieren können. */
readonly class GetImageDimensionsQuery
{
    public function __construct(private ImageStorageInterface $storage)
    {
    }

    /**
     * @param list<string> $urls
     *
     * @return array<string, ImageDimensions> nach URL; unbekannte, fremde oder vektorbasierte Bilder fehlen
     */
    public function execute(array $urls): array
    {
        return $urls === [] ? [] : $this->storage->dimensions(array_values(array_unique($urls)));
    }
}
