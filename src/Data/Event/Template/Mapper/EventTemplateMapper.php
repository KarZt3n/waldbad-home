<?php

namespace App\Data\Event\Template\Mapper;

use App\Data\Event\Template\Entity\EventTemplateActivityEntity;
use App\Data\Event\Template\Entity\EventTemplateCallToActionEntity;
use App\Data\Event\Template\Entity\EventTemplateEntity;
use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Template\Model\EventTemplate;
use App\Logic\Event\Template\Model\EventTemplateActivity;
use App\Logic\Event\Template\Model\EventTemplateCallToAction;

readonly class EventTemplateMapper
{
    public function toModel(EventTemplateEntity $entity): EventTemplate
    {
        return new EventTemplate(
            id: $entity->getId(),
            kind: EventScheduleKind::from($entity->getKind()),
            title: $entity->getTitle(),
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
            activities: array_map(
                static fn (EventTemplateActivityEntity $activity): EventTemplateActivity => new EventTemplateActivity(
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
                static fn (EventTemplateCallToActionEntity $action): EventTemplateCallToAction => new EventTemplateCallToAction(
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

    public function createEntity(EventTemplate $template): EventTemplateEntity
    {
        $entity = new EventTemplateEntity(
            id: $template->id,
            kind: $template->kind->value,
            title: $template->title,
            content: $template->content,
            mediaUrl: $template->mediaUrl,
            mediaAlt: $template->mediaAlt,
            mediaSource: $template->mediaSource,
            layout: $template->layout,
            imageWidthPercent: $template->imageWidthPercent,
            verticalAlignment: $template->verticalAlignment,
            textAlignment: $template->textAlignment,
            imageFit: $template->imageFit,
            helpEnabled: $template->helpEnabled,
            helpButtonLabel: $template->helpButtonLabel,
            createdAt: $template->createdAt,
            updatedAt: $template->updatedAt,
        );
        $entity->replaceActivities($this->activityEntities($template, $entity));
        $entity->replaceCallToActions($this->callToActionEntities($template, $entity));

        return $entity;
    }

    public function updateEntity(EventTemplate $template, EventTemplateEntity $entity): void
    {
        $entity->update(
            title: $template->title,
            content: $template->content,
            mediaUrl: $template->mediaUrl,
            mediaAlt: $template->mediaAlt,
            mediaSource: $template->mediaSource,
            layout: $template->layout,
            imageWidthPercent: $template->imageWidthPercent,
            verticalAlignment: $template->verticalAlignment,
            textAlignment: $template->textAlignment,
            imageFit: $template->imageFit,
            helpEnabled: $template->helpEnabled,
            helpButtonLabel: $template->helpButtonLabel,
            updatedAt: $template->updatedAt,
        );
        $entity->replaceActivities($this->activityEntities($template, $entity));
        $entity->replaceCallToActions($this->callToActionEntities($template, $entity));
    }

    /** @return list<EventTemplateActivityEntity> */
    private function activityEntities(EventTemplate $template, EventTemplateEntity $entity): array
    {
        return array_map(
            static fn (EventTemplateActivity $activity): EventTemplateActivityEntity => new EventTemplateActivityEntity(
                id: $activity->id,
                template: $entity,
                position: $activity->position,
                activityId: $activity->activityId,
                requiredHelpers: $activity->requiredHelpers,
                time: $activity->time,
                meetTime: $activity->meetTime,
                meetPlace: $activity->meetPlace,
                remark: $activity->remark,
            ),
            $template->activities,
        );
    }

    /** @return list<EventTemplateCallToActionEntity> */
    private function callToActionEntities(EventTemplate $template, EventTemplateEntity $entity): array
    {
        return array_map(
            static fn (EventTemplateCallToAction $action): EventTemplateCallToActionEntity => new EventTemplateCallToActionEntity(
                id: $action->id,
                template: $entity,
                position: $action->position,
                label: $action->label,
                url: $action->url,
                pageId: $action->pageId,
            ),
            $template->callToActions,
        );
    }
}
