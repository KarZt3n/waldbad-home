<?php

namespace App\Logic\Media\Image;

use App\Logic\Media\Image\Model\ImageDimensions;
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

    public function synchronizeMetadata(): int;

    /**
     * Liest die Maße direkt aus den Dateien, damit sie nach Ersetzen, Verschieben oder Löschen
     * ohne Cache-Invalidierung stimmen.
     *
     * @param list<string> $urls
     *
     * @return array<string, ImageDimensions> nach URL; fehlende Dateien und Bilder ohne Pixelmaße fehlen
     */
    public function dimensions(array $urls): array;
}
