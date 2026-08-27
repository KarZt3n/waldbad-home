<?php

namespace App\Data\Media\Image;

use App\Data\Media\Image\Entity\StoredImageEntity;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Media\Image\ImageStorageInterface;
use App\Logic\Media\Image\Model\ImageUpload;
use App\Logic\Media\Image\Model\StoredImage;
use Doctrine\ORM\EntityManagerInterface;

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
        private EntityManagerInterface $entityManager,
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

        $filename = $this->availableFilename($upload->originalName, self::EXTENSIONS[$mimeType]);
        $target = $this->mediaUploadDirectory.DIRECTORY_SEPARATOR.$filename;
        if (!move_uploaded_file($upload->temporaryPath, $target) && !rename($upload->temporaryPath, $target)) {
            throw new \RuntimeException('Das Bild konnte nicht gespeichert werden.');
        }
        $source = $this->normalizeSource($upload->source);
        $url = '/uploads/media/'.$filename;
        $now = new \DateTimeImmutable();
        $entity = new StoredImageEntity(
            id: $this->identifierGenerator->generate(),
            url: $url,
            originalName: basename($upload->originalName),
            mimeType: $mimeType,
            size: $upload->size,
            width: $dimensions[0],
            height: $dimensions[1],
            source: $source,
            createdAt: $now,
            updatedAt: $now,
        );
        try {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            @unlink($target);

            throw $exception;
        }

        return $this->storedImage($entity);
    }

    public function all(): array
    {
        return $this->images(200);
    }

    /**
     * @return list<StoredImage>
     */
    private function images(?int $limit): array
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
        $legacySourcePaths = [];
        $metadataImported = false;
        $selectedPaths = $limit === null ? $paths : array_slice($paths, 0, $limit);
        foreach ($selectedPaths as $path) {
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
            $url = '/uploads/media/'.$filename;
            $entity = $this->entityManager->getRepository(StoredImageEntity::class)->findOneBy(['url' => $url]);
            if (!$entity instanceof StoredImageEntity) {
                $now = new \DateTimeImmutable();
                $entity = new StoredImageEntity(
                    id: $this->identifierGenerator->generate(),
                    url: $url,
                    originalName: $filename,
                    mimeType: $mimeType,
                    size: $size,
                    width: $dimensions[0],
                    height: $dimensions[1],
                    source: $this->readLegacySource($path),
                    createdAt: $now,
                    updatedAt: $now,
                );
                $this->entityManager->persist($entity);
                $legacySourcePaths[] = $this->legacySourcePath($path);
                $metadataImported = true;
            }
            $images[] = $this->storedImage($entity);
        }
        if ($metadataImported) {
            $this->entityManager->flush();
        }
        foreach ($legacySourcePaths as $legacySourcePath) {
            if (is_file($legacySourcePath)) {
                @unlink($legacySourcePath);
            }
        }

        return $images;
    }

    public function updateSource(string $url, ?string $source): StoredImage
    {
        $path = $this->imagePathFromUrl($url);
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $dimensions = getimagesize($path);
        $size = filesize($path);
        if (!is_string($mimeType) || !isset(self::EXTENSIONS[$mimeType]) || $dimensions === false || $size === false) {
            throw new BusinessRuleViolationException('Das ausgewählte Bibliotheksbild ist ungültig.');
        }
        $normalizedSource = $this->normalizeSource($source);
        $entity = $this->entityManager->getRepository(StoredImageEntity::class)->findOneBy(['url' => $url]);
        $now = new \DateTimeImmutable();
        if (!$entity instanceof StoredImageEntity) {
            $entity = new StoredImageEntity(
                id: $this->identifierGenerator->generate(),
                url: $url,
                originalName: basename($path),
                mimeType: $mimeType,
                size: $size,
                width: $dimensions[0],
                height: $dimensions[1],
                source: $normalizedSource,
                createdAt: $now,
                updatedAt: $now,
            );
            $this->entityManager->persist($entity);
        } else {
            $entity->updateFileMetadata($entity->getOriginalName(), $mimeType, $size, $dimensions[0], $dimensions[1], $now);
            $entity->updateSource($normalizedSource, $now);
        }
        $this->entityManager->flush();

        return $this->storedImage($entity);
    }

    public function synchronizeMetadata(): int
    {
        return count($this->images(null));
    }

    private function imagePathFromUrl(string $url): string
    {
        $prefix = '/uploads/media/';
        if (!str_starts_with($url, $prefix)) {
            throw new BusinessRuleViolationException('Die Bildquelle kann nur für Bibliotheksbilder gespeichert werden.');
        }
        $filename = substr($url, strlen($prefix));
        if ($filename === '' || basename($filename) !== $filename) {
            throw new BusinessRuleViolationException('Die Bild-URL ist ungültig.');
        }
        $path = $this->mediaUploadDirectory.DIRECTORY_SEPARATOR.$filename;
        if (!is_file($path)) {
            throw new BusinessRuleViolationException('Das Bibliotheksbild wurde nicht gefunden.');
        }

        return $path;
    }

    private function availableFilename(string $originalName, string $extension): string
    {
        $basename = pathinfo(basename($originalName), PATHINFO_FILENAME);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $basename);
        if (is_string($transliterated)) {
            $basename = $transliterated;
        }
        $basename = mb_strtolower($basename);
        $basename = preg_replace('/[^a-z0-9]+/', '-', $basename);
        $basename = is_string($basename) ? trim($basename, '-') : '';
        $basename = mb_substr($basename === '' ? 'bild' : $basename, 0, 180);

        $filename = $basename.'.'.$extension;
        $suffix = 2;
        while (is_file($this->mediaUploadDirectory.DIRECTORY_SEPARATOR.$filename)) {
            $filename = $basename.'-'.$suffix.'.'.$extension;
            ++$suffix;
        }

        return $filename;
    }

    private function legacySourcePath(string $imagePath): string
    {
        return $imagePath.'.source.txt';
    }

    private function readLegacySource(string $imagePath): ?string
    {
        $sourcePath = $this->legacySourcePath($imagePath);
        if (!is_file($sourcePath)) {
            return null;
        }
        $source = file_get_contents($sourcePath);

        return $source === false ? null : $this->normalizeSource($source);
    }

    private function storedImage(StoredImageEntity $entity): StoredImage
    {
        return new StoredImage(
            url: $entity->getUrl(),
            originalName: $entity->getOriginalName(),
            mimeType: $entity->getMimeType(),
            size: $entity->getSize(),
            width: $entity->getWidth(),
            height: $entity->getHeight(),
            source: $entity->getSource(),
        );
    }

    private function normalizeSource(?string $source): ?string
    {
        if ($source === null || trim($source) === '') {
            return null;
        }
        $source = trim(strip_tags($source));
        if (mb_strlen($source) > 300) {
            throw new BusinessRuleViolationException('Die Bildquelle darf höchstens 300 Zeichen lang sein.');
        }

        return $source;
    }
}
