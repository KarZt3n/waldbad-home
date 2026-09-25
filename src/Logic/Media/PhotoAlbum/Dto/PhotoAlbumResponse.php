<?php

namespace App\Logic\Media\PhotoAlbum\Dto;

use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbumAction;

readonly class PhotoAlbumResponse
{
    /**
     * @param list<PhotoAlbumAction> $actions
     */
    public function __construct(
        public string $id,
        public string $title,
        public \DateTimeImmutable $date,
        public bool $visible,
        public array $actions,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromAlbum(PhotoAlbum $album): self
    {
        return new self($album->id, $album->title, $album->date, $album->visible, $album->actions, $album->createdAt, $album->updatedAt);
    }
}
