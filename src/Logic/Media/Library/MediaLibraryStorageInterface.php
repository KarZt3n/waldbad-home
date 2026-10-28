<?php

namespace App\Logic\Media\Library;

use App\Logic\Media\Library\Model\MediaFile;
use App\Logic\Media\Library\Model\MediaFolder;
use App\Logic\Media\Library\Model\MediaUpload;

/** Dateien und Ordner unterhalb von `/uploads`; Ordnerpfade sind relativ, `''` ist die Wurzel. */
interface MediaLibraryStorageInterface
{
    /**
     * @return list<MediaFolder>
     */
    public function folders(): array;

    public function folderExists(string $path): bool;

    public function createFolder(string $path): MediaFolder;

    public function deleteFolder(string $path): void;

    /**
     * @return list<MediaFile> neueste zuerst
     */
    public function files(string $folderPath, bool $recursive): array;

    public function find(string $url): ?MediaFile;

    public function store(string $folderPath, MediaUpload $upload): MediaFile;

    /** Überschreibt die Datei unter derselben URL; der Dateityp muss gleich bleiben. */
    public function overwrite(string $url, MediaUpload $upload): MediaFile;

    /** Verschiebt die Datei samt Metadaten; bei Namensgleichheit erhält sie im Zielordner einen freien Namen. */
    public function move(string $url, string $targetFolderPath): MediaFile;

    public function delete(string $url): void;
}
