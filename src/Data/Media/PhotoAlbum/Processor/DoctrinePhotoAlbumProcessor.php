<?php

namespace App\Data\Media\PhotoAlbum\Processor;

use App\Data\Media\PhotoAlbum\Entity\PhotoAlbumEntity;
use App\Data\Media\PhotoAlbum\Mapper\PhotoAlbumMapper;
use App\Logic\Media\PhotoAlbum\Exception\PhotoAlbumNotFoundException;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\PhotoAlbumProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrinePhotoAlbumProcessor implements PhotoAlbumProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PhotoAlbumMapper $mapper,
    ) {
    }

    public function save(PhotoAlbum $album): PhotoAlbum
    {
        $entity = $this->entityManager->find(PhotoAlbumEntity::class, $album->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($album);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($album, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(PhotoAlbumEntity::class, $id);
        if ($entity === null) {
            throw new PhotoAlbumNotFoundException($id);
        }
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
