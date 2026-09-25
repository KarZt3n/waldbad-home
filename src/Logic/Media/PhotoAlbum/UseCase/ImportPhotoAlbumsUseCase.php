<?php

namespace App\Logic\Media\PhotoAlbum\UseCase;

use App\Logic\Media\PhotoAlbum\Dto\ImportPhotoAlbumsResult;
use App\Logic\Media\PhotoAlbum\Dto\SavePhotoAlbumRequest;
use App\Logic\Media\PhotoAlbum\Manager\PhotoAlbumManagerInterface;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;

/**
 * Übernimmt Foto-Einträge (z. B. aus der Bestandswebsite). Wiederholbar: Einträge mit gleichem
 * Titel und Datum werden übersprungen, damit ein erneuter Import keine Duplikate erzeugt.
 */
readonly class ImportPhotoAlbumsUseCase
{
    public function __construct(
        private PhotoAlbumManagerInterface $manager,
        private SavePhotoAlbumUseCase $saveAlbum,
    ) {
    }

    /**
     * @param list<SavePhotoAlbumRequest> $requests
     */
    public function execute(array $requests): ImportPhotoAlbumsResult
    {
        $existingKeys = array_map(
            static fn (PhotoAlbum $album): string => self::key($album->title, $album->date),
            $this->manager->all(),
        );
        $created = 0;
        $skipped = 0;
        foreach ($requests as $request) {
            $key = self::key(trim($request->title), $request->date);
            if (in_array($key, $existingKeys, true)) {
                ++$skipped;
                continue;
            }
            $this->saveAlbum->execute($request);
            $existingKeys[] = $key;
            ++$created;
        }

        return new ImportPhotoAlbumsResult($created, $skipped);
    }

    private static function key(string $title, \DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d').'|'.mb_strtolower($title);
    }
}
