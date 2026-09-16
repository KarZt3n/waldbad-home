<?php

namespace App\Logic\Event\Template;

use App\Logic\Event\Template\Model\EventTemplate;

interface EventTemplateProviderInterface
{
    public function find(string $id): ?EventTemplate;

    /** @return list<EventTemplate> */
    public function findAll(): array;
}
