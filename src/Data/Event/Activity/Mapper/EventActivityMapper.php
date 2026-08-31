<?php

namespace App\Data\Event\Activity\Mapper;

use App\Data\Event\Activity\Entity\EventActivityEntity;
use App\Logic\Event\Activity\Model\EventActivity;

readonly class EventActivityMapper
{
    public function toModel(EventActivityEntity $entity): EventActivity
    {
        return new EventActivity(
            id: $entity->getId(),
            name: $entity->getName(),
            description: $entity->getDescription(),
            active: $entity->isActive(),
            defaultRequiredHelpers: $entity->getDefaultRequiredHelpers(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function createEntity(EventActivity $activity): EventActivityEntity
    {
        return new EventActivityEntity(
            id: $activity->id,
            name: $activity->name,
            description: $activity->description,
            active: $activity->active,
            defaultRequiredHelpers: $activity->defaultRequiredHelpers,
            createdAt: $activity->createdAt,
            updatedAt: $activity->updatedAt,
        );
    }

    public function updateEntity(EventActivity $activity, EventActivityEntity $entity): void
    {
        $entity->update($activity->name, $activity->description, $activity->active, $activity->defaultRequiredHelpers, $activity->updatedAt);
    }
}
