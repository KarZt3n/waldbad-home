<?php

namespace App\Logic\Event\Template\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Event\Template\Dto\CreateEventTemplateRequest;
use App\Logic\Event\Template\Dto\EventTemplateResponse;
use App\Logic\Event\Template\Manager\EventTemplateManagerInterface;
use App\Logic\Event\Template\Model\EventTemplate;

readonly class CreateEventTemplateUseCase
{
    public function __construct(
        private EventTemplateManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(CreateEventTemplateRequest $request): EventTemplateResponse
    {
        $now = $this->clock->now();

        $template = new EventTemplate(
            id: $this->identifierGenerator->generate(),
            kind: $request->kind,
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
            createdAt: $now,
            updatedAt: $now,
        );

        return EventTemplateResponse::fromTemplate($this->manager->save($template));
    }
}
