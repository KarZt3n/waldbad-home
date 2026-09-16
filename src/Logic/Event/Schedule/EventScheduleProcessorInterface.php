<?php

namespace App\Logic\Event\Schedule;

use App\Logic\Event\Schedule\Model\EventSchedule;

interface EventScheduleProcessorInterface
{
    public function save(EventSchedule $schedule): EventSchedule;

    public function delete(string $id): void;

    /**
     * Entfernt eine gelöschte Aktivität aus allen Veranstaltungen/Arbeitseinsätzen, die sie
     * zuordnen (siehe `App\Logic\Event\Activity\UseCase\DeleteEventActivityUseCase`) — ein
     * gezieltes Bulk-Delete der betroffenen Zuordnungszeilen, ohne die ganzen Veranstaltungen neu
     * zu laden/speichern.
     */
    public function removeActivityReferences(string $activityId): void;
}
