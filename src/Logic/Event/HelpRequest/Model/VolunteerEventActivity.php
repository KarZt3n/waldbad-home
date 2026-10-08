<?php

namespace App\Logic\Event\HelpRequest\Model;

/**
 * Aktivität einer Veranstaltung aus Sicht der Helferanmeldung. Nur auswählbare Aktivitäten werden als
 * Checkbox mit Helferzahl angeboten; alle übrigen dienen ausschließlich als Auflistung.
 */
readonly class VolunteerEventActivity
{
    public function __construct(
        public string $activityId,
        public ?int $requiredHelpers,
        public bool $selectable,
        public ?string $time = null,
        public ?string $meetTime = null,
        public ?string $meetPlace = null,
        public ?string $remark = null,
    ) {
    }
}
