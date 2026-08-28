<?php

namespace App\Data\Event\Schedule\Mapper;

use App\Data\Event\Schedule\Entity\EventScheduleActivityEntity;
use App\Data\Event\Schedule\Entity\EventScheduleCallToActionEntity;
use App\Data\Event\Schedule\Entity\EventScheduleEntity;
use App\Logic\Event\Schedule\Model\EventSchedule;
use App\Logic\Event\Schedule\Model\EventScheduleActivity;
use App\Logic\Event\Schedule\Model\EventScheduleCallToAction;
use App\Logic\Event\Schedule\Model\EventScheduleKind;

readonly class EventScheduleMapper
{
    public function toModel(EventScheduleEntity $entity): EventSchedule
    {
        return new EventSchedule(
            id: $entity->getId(),
            kind: EventScheduleKind::from($entity->getKind()),
            title: $entity->getTitle(),
            date: $entity->getDate(),
            time: $entity->getTime(),
            content: $entity->getContent(),
            mediaUrl: $entity->getMediaUrl(),
            mediaAlt: $entity->getMediaAlt(),
            mediaSource: $entity->getMediaSource(),
            layout: $entity->getLayout(),
            imageWidthPercent: $entity->getImageWidthPercent(),
            verticalAlignment: $entity->getVerticalAlignment(),
            textAlignment: $entity->getTextAlignment(),
            imageFit: $entity->getImageFit(),
            helpEnabled: $entity->isHelpEnabled(),
            helpButtonLabel: $entity->getHelpButtonLabel(),
            visible: $entity->isVisible(),
            activities: array_map(
                static fn (EventScheduleActivityEntity $activity): EventScheduleActivity => new EventScheduleActivity(
                    id: $activity->getId(),
                    position: $activity->getPosition(),
                    activityId: $activity->getActivityId(),
                    requiredHelpers: $activity->getRequiredHelpers(),
                    time: $activity->getTime(),
                    meetTime: $activity->getMeetTime(),
                    meetPlace: $activity->getMeetPlace(),
                    remark: $activity->getRemark(),
                ),
                $entity->getActivities(),
            ),
            callToActions: array_map(
                static fn (EventScheduleCallToActionEntity $action): EventScheduleCallToAction => new EventScheduleCallToAction(
                    id: $action->getId(),
                    position: $action->getPosition(),
                    label: $action->getLabel(),
                    url: $action->getUrl(),
                    pageId: $action->getPageId(),
                ),
                $entity->getCallToActions(),
            ),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function createEntity(EventSchedule $schedule): EventScheduleEntity
    {
        $entity = new EventScheduleEntity(
            id: $schedule->id,
            kind: $schedule->kind->value,
            title: $schedule->title,
            date: $schedule->date,
            time: $schedule->time,
            content: $schedule->content,
            mediaUrl: $schedule->mediaUrl,
            mediaAlt: $schedule->mediaAlt,
            mediaSource: $schedule->mediaSource,
            layout: $schedule->layout,
            imageWidthPercent: $schedule->imageWidthPercent,
            verticalAlignment: $schedule->verticalAlignment,
            textAlignment: $schedule->textAlignment,
            imageFit: $schedule->imageFit,
            helpEnabled: $schedule->helpEnabled,
            helpButtonLabel: $schedule->helpButtonLabel,
            visible: $schedule->visible,
            createdAt: $schedule->createdAt,
            updatedAt: $schedule->updatedAt,
        );
        $entity->replaceActivities($this->activityEntities($schedule, $entity));
        $entity->replaceCallToActions($this->callToActionEntities($schedule, $entity));

        return $entity;
    }

    public function updateEntity(EventSchedule $schedule, EventScheduleEntity $entity): void
    {
        $entity->update(
            title: $schedule->title,
            date: $schedule->date,
            time: $schedule->time,
            content: $schedule->content,
            mediaUrl: $schedule->mediaUrl,
            mediaAlt: $schedule->mediaAlt,
            mediaSource: $schedule->mediaSource,
            layout: $schedule->layout,
            imageWidthPercent: $schedule->imageWidthPercent,
            verticalAlignment: $schedule->verticalAlignment,
            textAlignment: $schedule->textAlignment,
            imageFit: $schedule->imageFit,
            helpEnabled: $schedule->helpEnabled,
            helpButtonLabel: $schedule->helpButtonLabel,
            visible: $schedule->visible,
            updatedAt: $schedule->updatedAt,
        );
        $entity->replaceActivities($this->activityEntities($schedule, $entity));
        $entity->replaceCallToActions($this->callToActionEntities($schedule, $entity));
    }

    /** @return list<EventScheduleActivityEntity> */
    private function activityEntities(EventSchedule $schedule, EventScheduleEntity $entity): array
    {
        return array_map(
            static fn (EventScheduleActivity $activity): EventScheduleActivityEntity => new EventScheduleActivityEntity(
                id: $activity->id,
                schedule: $entity,
                position: $activity->position,
                activityId: $activity->activityId,
                requiredHelpers: $activity->requiredHelpers,
                time: $activity->time,
                meetTime: $activity->meetTime,
                meetPlace: $activity->meetPlace,
                remark: $activity->remark,
            ),
            $schedule->activities,
        );
    }

    /** @return list<EventScheduleCallToActionEntity> */
    private function callToActionEntities(EventSchedule $schedule, EventScheduleEntity $entity): array
    {
        return array_map(
            static fn (EventScheduleCallToAction $action): EventScheduleCallToActionEntity => new EventScheduleCallToActionEntity(
                id: $action->id,
                schedule: $entity,
                position: $action->position,
                label: $action->label,
                url: $action->url,
                pageId: $action->pageId,
            ),
            $schedule->callToActions,
        );
    }
}
