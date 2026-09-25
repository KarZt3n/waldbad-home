<?php

namespace App\Data\Media\PhotoAlbum\Provider;

use App\Data\Media\PhotoAlbum\Entity\PhotoAlbumEntity;
use App\Data\Media\PhotoAlbum\Mapper\PhotoAlbumMapper;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\PhotoAlbumProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrinePhotoAlbumProvider implements PhotoAlbumProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PhotoAlbumMapper $mapper,
    ) {
    }

    public function find(string $id): ?PhotoAlbum
    {
        $entity = $this->entityManager->find(PhotoAlbumEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(PhotoAlbumEntity::class)->findBy([], ['date' => 'DESC', 'createdAt' => 'DESC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
