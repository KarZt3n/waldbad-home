<?php

namespace App\Data\Media\Image;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Media\Image\ImageStorageInterface;
use App\Logic\Media\Image\Model\ImageUpload;
use App\Logic\Media\Image\Model\StoredImage;

readonly class LocalImageStorage implements ImageStorageInterface
{
    private const array EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private IdentifierGeneratorInterface $identifierGenerator,
        private string $mediaUploadDirectory,
    ) {
    }

    public function store(ImageUpload $upload): StoredImage
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($upload->temporaryPath);
        if (!is_string($mimeType) || !isset(self::EXTENSIONS[$mimeType])) {
            throw new BusinessRuleViolationException('Erlaubt sind ausschließlich JPEG-, PNG-, WebP- und GIF-Bilder.');
        }

        $dimensions = getimagesize($upload->temporaryPath);
        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            throw new BusinessRuleViolationException('Die hochgeladene Datei ist kein gültiges Bild.');
        }

        if (!is_dir($this->mediaUploadDirectory)
            && !mkdir($this->mediaUploadDirectory, 0775, true)
            && !is_dir($this->mediaUploadDirectory)) {
            throw new \RuntimeException('Das Medienverzeichnis konnte nicht angelegt werden.');
        }

        $filename = $this->identifierGenerator->generate().'.'.self::EXTENSIONS[$mimeType];
        $target = $this->mediaUploadDirectory.DIRECTORY_SEPARATOR.$filename;
        if (!move_uploaded_file($upload->temporaryPath, $target) && !rename($upload->temporaryPath, $target)) {
            throw new \RuntimeException('Das Bild konnte nicht gespeichert werden.');
        }

        return new StoredImage(
            url: '/uploads/media/'.$filename,
            originalName: $upload->originalName,
            mimeType: $mimeType,
            size: $upload->size,
            width: $dimensions[0],
            height: $dimensions[1],
        );
    }

    public function all(): array
    {
        if (!is_dir($this->mediaUploadDirectory)) {
            return [];
        }

        $paths = glob($this->mediaUploadDirectory.DIRECTORY_SEPARATOR.'*');
        if ($paths === false) {
            throw new \RuntimeException('Das Medienverzeichnis konnte nicht gelesen werden.');
        }

        usort($paths, static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));
        $images = [];
        foreach (array_slice($paths, 0, 200) as $path) {
            if (!is_file($path)) {
                continue;
            }

            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            $dimensions = getimagesize($path);
            $size = filesize($path);
            if (!is_string($mimeType) || !isset(self::EXTENSIONS[$mimeType]) || $dimensions === false || $size === false) {
                continue;
            }

            $filename = basename($path);
            $images[] = new StoredImage(
                url: '/uploads/media/'.$filename,
                originalName: $filename,
                mimeType: $mimeType,
                size: $size,
                width: $dimensions[0],
                height: $dimensions[1],
            );
        }

        return $images;
    }
}
