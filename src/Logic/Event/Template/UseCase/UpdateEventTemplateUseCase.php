<?php

namespace App\Logic\Event\Template\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\Template\Dto\EventTemplateResponse;
use App\Logic\Event\Template\Dto\UpdateEventTemplateRequest;
use App\Logic\Event\Template\Manager\EventTemplateManagerInterface;

readonly class UpdateEventTemplateUseCase
{
    public function __construct(
        private EventTemplateManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(UpdateEventTemplateRequest $request): EventTemplateResponse
    {
        $template = $this->manager->get($request->id)->revise(
            title: $request->title,
            content: $request->content,
            mediaUrl: $request->mediaUrl,
            mediaAlt: $request->mediaAlt,
            mediaSource: $request->mediaSource,
            layout: $request->layout,
            imageWidthPercent: $request->imageWidthPercent,
            verticalAlignment: $request->verticalAlignment,
            textAlignment: $request->textAlignment,
            imageFit: $request->imageFit,
            helpEnabled: $request->helpEnabled,
            helpButtonLabel: $request->helpButtonLabel,
            activities: $request->activities,
            callToActions: $request->callToActions,
            updatedAt: $this->clock->now(),
        );

        return EventTemplateResponse::fromTemplate($this->manager->save($template));
    }
}
