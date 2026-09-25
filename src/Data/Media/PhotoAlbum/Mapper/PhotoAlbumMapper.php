<?php

namespace App\Data\Media\PhotoAlbum\Mapper;

use App\Data\Media\PhotoAlbum\Entity\PhotoAlbumActionEntity;
use App\Data\Media\PhotoAlbum\Entity\PhotoAlbumEntity;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbumAction;

readonly class PhotoAlbumMapper
{
    public function toModel(PhotoAlbumEntity $entity): PhotoAlbum
    {
        return new PhotoAlbum(
            id: $entity->getId(),
            title: $entity->getTitle(),
            date: $entity->getDate(),
            visible: $entity->isVisible(),
            actions: array_map(
                static fn (PhotoAlbumActionEntity $action): PhotoAlbumAction => new PhotoAlbumAction(
                    id: $action->getId(),
                    position: $action->getPosition(),
                    label: $action->getLabel(),
                    url: $action->getUrl(),
                    pageId: $action->getPageId(),
                ),
                $entity->getActions(),
            ),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function createEntity(PhotoAlbum $album): PhotoAlbumEntity
    {
        $entity = new PhotoAlbumEntity(
            id: $album->id,
            title: $album->title,
            date: $album->date,
            visible: $album->visible,
            createdAt: $album->createdAt,
            updatedAt: $album->updatedAt,
        );
        $entity->replaceActions($this->actionEntities($album, $entity));

        return $entity;
    }

    public function updateEntity(PhotoAlbum $album, PhotoAlbumEntity $entity): void
    {
        $entity->update($album->title, $album->date, $album->visible, $album->updatedAt);
        $entity->replaceActions($this->actionEntities($album, $entity));
    }

    /** @return list<PhotoAlbumActionEntity> */
    private function actionEntities(PhotoAlbum $album, PhotoAlbumEntity $entity): array
    {
        return array_map(
            static fn (PhotoAlbumAction $action): PhotoAlbumActionEntity => new PhotoAlbumActionEntity(
                id: $action->id,
                album: $entity,
                position: $action->position,
                label: $action->label,
                url: $action->url,
                pageId: $action->pageId,
            ),
            $album->actions,
        );
    }
}
