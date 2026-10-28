<?php

namespace App\Logic\Media\Library\Model;

readonly class MediaFile
{
    public function __construct(
        public string $url,
        public string $folderPath,
        public string $originalName,
        public string $mimeType,
        public int $size,
        public ?int $width,
        public ?int $height,
        public ?string $source,
        public \DateTimeImmutable $modifiedAt,
    ) {
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function filenameWithoutExtension(): string
    {
        return pathinfo($this->url, PATHINFO_FILENAME);
    }
}
