<?php

namespace App\Logic\Event\Template\Manager;

use App\Logic\Event\Template\EventTemplateProcessorInterface;
use App\Logic\Event\Template\EventTemplateProviderInterface;
use App\Logic\Event\Template\Exception\EventTemplateNotFoundException;
use App\Logic\Event\Template\Model\EventTemplate;

readonly class EventTemplateManager implements EventTemplateManagerInterface
{
    public function __construct(
        private EventTemplateProviderInterface $provider,
        private EventTemplateProcessorInterface $processor,
    ) {
    }

    public function get(string $id): EventTemplate
    {
        return $this->provider->find($id) ?? throw new EventTemplateNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function save(EventTemplate $template): EventTemplate
    {
        return $this->processor->save($template);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }

    public function removeActivityReferences(string $activityId): void
    {
        $this->processor->removeActivityReferences($activityId);
    }
}
