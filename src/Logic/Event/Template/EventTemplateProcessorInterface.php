<?php

namespace App\Logic\Event\Template;

use App\Logic\Event\Template\Model\EventTemplate;

interface EventTemplateProcessorInterface
{
    public function save(EventTemplate $template): EventTemplate;

    public function delete(string $id): void;

    /**
     * Entfernt eine gelöschte Aktivität aus allen Vorlagen, die sie zuordnen (siehe
     * `App\Logic\Event\Activity\UseCase\DeleteEventActivityUseCase`) — ein gezieltes Bulk-Delete
     * der betroffenen Zuordnungszeilen, ohne die ganzen Vorlagen neu zu laden/speichern.
     */
    public function removeActivityReferences(string $activityId): void;
}
