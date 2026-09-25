<?php

namespace App\Logic\Media\PhotoAlbum\Dto;

/** Eingabe für Anlegen (`$id === null`) und Bearbeiten eines Foto-Eintrags. */
readonly class SavePhotoAlbumRequest
{
    /**
     * @param list<PhotoAlbumActionInput> $actions
     */
    public function __construct(
        public ?string $id,
        public string $title,
        public \DateTimeImmutable $date,
        public bool $visible,
        public array $actions,
    ) {
    }
}
