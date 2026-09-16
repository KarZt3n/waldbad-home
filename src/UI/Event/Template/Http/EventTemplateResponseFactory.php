<?php

namespace App\UI\Event\Template\Http;

use App\Logic\Event\Template\Dto\EventTemplateResponse;
use App\Logic\Event\Template\Model\EventTemplateActivity;
use App\Logic\Event\Template\Model\EventTemplateCallToAction;

readonly class EventTemplateResponseFactory
{
    /** @return array<string, mixed> */
    public function template(EventTemplateResponse $template): array
    {
        return [
            'id' => $template->id,
            'kind' => $template->kind->value,
            'title' => $template->title,
            'content' => $template->content,
            'mediaUrl' => $template->mediaUrl,
            'mediaAlt' => $template->mediaAlt,
            'mediaSource' => $template->mediaSource,
            'layout' => $template->layout,
            'imageWidthPercent' => $template->imageWidthPercent,
            'verticalAlignment' => $template->verticalAlignment,
            'textAlignment' => $template->textAlignment,
            'imageFit' => $template->imageFit,
            'helpEnabled' => $template->helpEnabled,
            'helpButtonLabel' => $template->helpButtonLabel,
            'activities' => array_map(
                static fn (EventTemplateActivity $activity): array => [
                    'activityId' => $activity->activityId,
                    'requiredHelpers' => $activity->requiredHelpers,
                    'time' => $activity->time,
                    'meetTime' => $activity->meetTime,
                    'meetPlace' => $activity->meetPlace,
                    'remark' => $activity->remark,
                ],
                $template->activities,
            ),
            'callToActions' => array_map(
                static fn (EventTemplateCallToAction $action): array => [
                    'label' => $action->label,
                    'url' => $action->url,
                    'pageId' => $action->pageId,
                ],
                $template->callToActions,
            ),
            'createdAt' => $template->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $template->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<EventTemplateResponse> $templates
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function collection(array $templates): array
    {
        return ['items' => array_map($this->template(...), $templates), 'total' => count($templates)];
    }
}
