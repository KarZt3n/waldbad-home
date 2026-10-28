<?php

namespace App\Data\Media\Library;

use App\Data\Media\Image\Entity\StoredImageEntity;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Media\Library\Exception\MediaFileNotFoundException;
use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\Model\MediaFile;
use App\Logic\Media\Library\Model\MediaFolder;
use App\Logic\Media\Library\Model\MediaUpload;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Metadaten (Originalname, Quelle) werden wie bisher nur für Bilder in `media_image` gespeichert;
 * alle übrigen Dateien beschreibt allein das Dateisystem.
 */
readonly class LocalMediaLibraryStorage implements MediaLibraryStorageInterface
{
    private const array IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    private const array DOCUMENT_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'audio/mpeg' => 'mp3',
        'video/mp4' => 'mp4',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
    ];

    public function __construct(
        private UploadDirectory $uploads,
        private IdentifierGeneratorInterface $identifierGenerator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function folders(): array
    {
        return array_map(
            fn (string $path): MediaFolder => new MediaFolder(path: $path, fileCount: count($this->uploads->files($path, false))),
            $this->uploads->folders(),
        );
    }

    public function folderExists(string $path): bool
    {
        return $this->uploads->existingFolderPath($path) !== null;
    }

    public function createFolder(string $path): MediaFolder
    {
        $directory = $this->uploads->folderPath($path) ?? throw new BusinessRuleViolationException('Der Ordnerpfad ist ungültig.');
        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Der Ordner konnte nicht angelegt werden.');
        }

        return new MediaFolder(path: $path, fileCount: 0);
    }

    public function deleteFolder(string $path): void
    {
        $directory = $this->uploads->existingFolderPath($path);
        if ($directory === null || $path === '') {
            return;
        }
        if (!@rmdir($directory)) {
            throw new BusinessRuleViolationException('Der Ordner enthält noch versteckte Dateien und kann nicht gelöscht werden.');
        }
    }

    public function files(string $folderPath, bool $recursive): array
    {
        $entities = [];
        foreach ($this->entityManager->getRepository(StoredImageEntity::class)->findAll() as $entity) {
            $entities[$entity->getUrl()] = $entity;
        }
        $files = [];
        foreach ($this->uploads->files($folderPath, $recursive) as $path) {
            $file = $this->mediaFile($path, $entities[$this->uploads->urlFromPath($path)] ?? null);
            if ($file !== null) {
                $files[] = $file;
            }
        }
        usort($files, static fn (MediaFile $left, MediaFile $right): int => $right->modifiedAt <=> $left->modifiedAt);

        return $files;
    }

    public function find(string $url): ?MediaFile
    {
        $path = $this->uploads->filePathFromUrl($url);

        return $path === null ? null : $this->mediaFile($path, $this->entity($url));
    }

    public function store(string $folderPath, MediaUpload $upload): MediaFile
    {
        $directory = $this->uploads->existingFolderPath($folderPath) ?? throw new BusinessRuleViolationException('Der Zielordner existiert nicht.');
        [$mimeType, $extension] = $this->detectType($upload->temporaryPath);
        $dimensions = $this->dimensions($upload->temporaryPath, $mimeType);

        $target = $directory.DIRECTORY_SEPARATOR.$this->uploads->availableFilename($directory, $upload->originalName, $extension);
        $this->moveUpload($upload->temporaryPath, $target);
        if ($dimensions !== null) {
            $now = new \DateTimeImmutable();
            try {
                $this->entityManager->persist(new StoredImageEntity(
                    id: $this->identifierGenerator->generate(),
                    url: $this->uploads->urlFromPath($target),
                    originalName: basename($upload->originalName),
                    mimeType: $mimeType,
                    size: $upload->size,
                    width: $dimensions[0],
                    height: $dimensions[1],
                    source: $this->normalizeSource($upload->source),
                    createdAt: $now,
                    updatedAt: $now,
                ));
                $this->entityManager->flush();
            } catch (\Throwable $exception) {
                @unlink($target);

                throw $exception;
            }
        }

        return $this->find($this->uploads->urlFromPath($target)) ?? throw new \RuntimeException('Die Datei konnte nicht gespeichert werden.');
    }

    public function overwrite(string $url, MediaUpload $upload): MediaFile
    {
        $target = $this->uploads->filePathFromUrl($url) ?? throw new MediaFileNotFoundException($url);
        [$mimeType, $extension] = $this->detectType($upload->temporaryPath);
        if (strtolower(pathinfo($target, PATHINFO_EXTENSION)) !== $extension) {
            throw new BusinessRuleViolationException(sprintf(
                'Die Datei kann nur durch eine Datei desselben Typs (.%s) ersetzt werden, damit bestehende Links gültig bleiben.',
                pathinfo($target, PATHINFO_EXTENSION),
            ));
        }
        if (isset(self::IMAGE_EXTENSIONS[$mimeType])) {
            throw new BusinessRuleViolationException('Bilder werden nicht überschrieben, sondern unter neuer URL ersetzt.');
        }
        $this->moveUpload($upload->temporaryPath, $target);
        clearstatcache(true, $target);

        return $this->find($url) ?? throw new MediaFileNotFoundException($url);
    }

    public function move(string $url, string $targetFolderPath): MediaFile
    {
        $source = $this->uploads->filePathFromUrl($url) ?? throw new MediaFileNotFoundException($url);
        $directory = $this->uploads->existingFolderPath($targetFolderPath) ?? throw new BusinessRuleViolationException('Der Zielordner existiert nicht.');
        $filename = basename($source);
        $target = $directory.DIRECTORY_SEPARATOR.$filename;
        if (file_exists($target)) {
            $target = $directory.DIRECTORY_SEPARATOR.$this->uploads->availableFilename(
                $directory,
                pathinfo($filename, PATHINFO_FILENAME),
                pathinfo($filename, PATHINFO_EXTENSION),
            );
        }

        return $this->relocate($url, $source, $target, null);
    }

    public function rename(string $url, string $filename): MediaFile
    {
        $source = $this->uploads->filePathFromUrl($url) ?? throw new MediaFileNotFoundException($url);
        $target = dirname($source).DIRECTORY_SEPARATOR.$this->uploads->normalizedBasename($filename).'.'.pathinfo($source, PATHINFO_EXTENSION);
        if ($target === $source) {
            return $this->find($url) ?? throw new MediaFileNotFoundException($url);
        }
        if (file_exists($target)) {
            throw new BusinessRuleViolationException(sprintf('Im Ordner gibt es bereits eine Datei „%s“.', basename($target)));
        }

        return $this->relocate($url, $source, $target, basename($target));
    }

    public function delete(string $url): void
    {
        $path = $this->uploads->filePathFromUrl($url);
        if ($path !== null && !unlink($path)) {
            throw new \RuntimeException('Die Datei konnte nicht gelöscht werden.');
        }
        if ($path !== null && is_file($path.'.source.txt')) {
            @unlink($path.'.source.txt');
        }

        $entity = $this->entity($url);
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    /**
     * Benennt die Datei auf der Platte um und zieht Bildmetadaten nach; `$originalName` ersetzt den
     * angezeigten Namen, sonst bleibt er.
     */
    private function relocate(string $url, string $source, string $target, ?string $originalName): MediaFile
    {
        if (!rename($source, $target)) {
            throw new \RuntimeException('Die Datei konnte nicht verschoben werden.');
        }
        if (is_file($source.'.source.txt')) {
            @rename($source.'.source.txt', $target.'.source.txt');
        }

        $targetUrl = $this->uploads->urlFromPath($target);
        $entity = $this->entity($url);
        if ($entity !== null) {
            try {
                $now = new \DateTimeImmutable();
                $entity->relocate($targetUrl, $now);
                if ($originalName !== null) {
                    $entity->updateFileMetadata($originalName, $entity->getMimeType(), $entity->getSize(), $entity->getWidth(), $entity->getHeight(), $now);
                }
                $this->entityManager->flush();
            } catch (\Throwable $exception) {
                @rename($target, $source);

                throw $exception;
            }
        }

        return $this->find($targetUrl) ?? throw new MediaFileNotFoundException($targetUrl);
    }

    private function mediaFile(string $path, ?StoredImageEntity $entity): ?MediaFile
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $size = filesize($path);
        $modifiedAt = filemtime($path);
        if (!is_string($mimeType) || $size === false || $modifiedAt === false) {
            return null;
        }
        $dimensions = $entity === null ? $this->dimensions($path, $mimeType) : [$entity->getWidth(), $entity->getHeight()];

        return new MediaFile(
            url: $this->uploads->urlFromPath($path),
            folderPath: $this->uploads->folderOfPath($path),
            originalName: $entity?->getOriginalName() ?? basename($path),
            mimeType: $mimeType,
            size: $size,
            width: $dimensions[0] ?? null,
            height: $dimensions[1] ?? null,
            source: $entity?->getSource(),
            modifiedAt: (new \DateTimeImmutable())->setTimestamp($modifiedAt),
        );
    }

    /**
     * @return array{string, string} MIME-Typ und Dateiendung
     */
    private function detectType(string $path): array
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = is_string($mimeType) ? (self::IMAGE_EXTENSIONS[$mimeType] ?? self::DOCUMENT_EXTENSIONS[$mimeType] ?? null) : null;
        if (!is_string($mimeType) || $extension === null) {
            throw new BusinessRuleViolationException('Erlaubt sind Bilder (JPEG, PNG, WebP, GIF), PDF, MP3, MP4 sowie Word-, Excel-, PowerPoint- und OpenDocument-Dateien.');
        }

        return [$mimeType, $extension];
    }

    /**
     * @return array{int, int}|null
     */
    private function dimensions(string $path, string $mimeType): ?array
    {
        if (!isset(self::IMAGE_EXTENSIONS[$mimeType])) {
            return null;
        }
        $dimensions = getimagesize($path);
        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            throw new BusinessRuleViolationException('Die hochgeladene Datei ist kein gültiges Bild.');
        }

        return [$dimensions[0], $dimensions[1]];
    }

    private function moveUpload(string $temporaryPath, string $target): void
    {
        if (!move_uploaded_file($temporaryPath, $target) && !rename($temporaryPath, $target)) {
            throw new \RuntimeException('Die Datei konnte nicht gespeichert werden.');
        }
    }

    private function entity(string $url): ?StoredImageEntity
    {
        $entity = $this->entityManager->getRepository(StoredImageEntity::class)->findOneBy(['url' => $url]);

        return $entity instanceof StoredImageEntity ? $entity : null;
    }

    private function normalizeSource(?string $source): ?string
    {
        if ($source === null || trim($source) === '') {
            return null;
        }

        return trim(strip_tags($source));
    }
}
