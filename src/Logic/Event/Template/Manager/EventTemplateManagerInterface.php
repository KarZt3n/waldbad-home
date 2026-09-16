<?php

namespace App\Logic\Event\Template\Manager;

use App\Logic\Event\Template\Model\EventTemplate;

interface EventTemplateManagerInterface
{
    public function get(string $id): EventTemplate;

    /** @return list<EventTemplate> */
    public function all(): array;

    public function save(EventTemplate $template): EventTemplate;

    public function delete(string $id): void;

    public function removeActivityReferences(string $activityId): void;
}
