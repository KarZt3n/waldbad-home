<?php

namespace App\Data\Media\PhotoAlbum\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'photo_album')]
#[ORM\Index(name: 'idx_photo_album_visible_date', columns: ['visible', 'album_date'])]
class PhotoAlbumEntity
{
    /** @var Collection<int, PhotoAlbumActionEntity> */
    #[ORM\OneToMany(targetEntity: PhotoAlbumActionEntity::class, mappedBy: 'album', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $actions;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 250)]
        private string $title,
        #[ORM\Column(name: 'album_date', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $date,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $visible,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->actions = new ArrayCollection();
    }

    public function getId(): string { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function isVisible(): bool { return $this->visible; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return list<PhotoAlbumActionEntity> */
    public function getActions(): array { return array_values($this->actions->toArray()); }

    public function update(string $title, \DateTimeImmutable $date, bool $visible, \DateTimeImmutable $updatedAt): void
    {
        $this->title = $title;
        $this->date = $date;
        $this->visible = $visible;
        $this->updatedAt = $updatedAt;
    }

    /** @param list<PhotoAlbumActionEntity> $actions */
    public function replaceActions(array $actions): void
    {
        $this->actions->clear();
        foreach ($actions as $action) {
            $this->actions->add($action);
        }
    }
}
