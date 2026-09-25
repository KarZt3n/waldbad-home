<?php

namespace App\Data\Media\PhotoAlbum\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'photo_album_action')]
#[ORM\Index(name: 'idx_photo_album_action_album_position', columns: ['album_id', 'position'])]
class PhotoAlbumActionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: PhotoAlbumEntity::class, inversedBy: 'actions')]
        #[ORM\JoinColumn(name: 'album_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private PhotoAlbumEntity $album,
        #[ORM\Column(type: Types::INTEGER)]
        private int $position,
        #[ORM\Column(type: Types::STRING, length: 80)]
        private string $label,
        #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
        private ?string $url,
        #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
        private ?string $pageId,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getAlbum(): PhotoAlbumEntity { return $this->album; }
    public function getPosition(): int { return $this->position; }
    public function getLabel(): string { return $this->label; }
    public function getUrl(): ?string { return $this->url; }
    public function getPageId(): ?string { return $this->pageId; }
}
