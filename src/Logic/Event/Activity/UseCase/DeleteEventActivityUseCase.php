<?php

namespace App\Logic\Event\Activity\UseCase;

use App\Logic\Event\Activity\Manager\EventActivityManagerInterface;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;
use App\Logic\Event\Template\Manager\EventTemplateManagerInterface;

/**
 * Löscht eine Aktivität endgültig (anders als der bestehende „aktiv"-Schalter, siehe
 * `UpdateEventActivityUseCase`) und entfernt sie zuvor aus jeder Veranstaltung/jedem Arbeitseinsatz
 * und jeder Vorlage, die sie zuordnen — sonst blieben verwaiste `activityId`-Referenzen zurück
 * (`event_schedule_activity`/`event_template_activity` haben bewusst keinen Fremdschlüssel auf
 * `event_activity`, siehe dortige Migrationen). Die Cross-Domain-Abhängigkeit über die
 * Manager-Interfaces von Schedule und Template folgt demselben Muster wie
 * `ImportEventContentBlocksUseCase`.
 */
readonly class DeleteEventActivityUseCase
{
    public function __construct(
        private EventActivityManagerInterface $activities,
        private EventScheduleManagerInterface $schedules,
        private EventTemplateManagerInterface $templates,
    ) {
    }

    public function execute(string $id): void
    {
        $this->activities->get($id);
        $this->schedules->removeActivityReferences($id);
        $this->templates->removeActivityReferences($id);
        $this->activities->delete($id);
    }
}
