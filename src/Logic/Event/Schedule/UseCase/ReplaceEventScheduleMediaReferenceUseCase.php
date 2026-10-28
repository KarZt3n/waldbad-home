<?php

namespace App\Logic\Event\Schedule\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;

readonly class ReplaceEventScheduleMediaReferenceUseCase
{
    public function __construct(
        private EventScheduleManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int Anzahl der geänderten Veranstaltungen
     */
    public function execute(string $url, ?string $replacementUrl): int
    {
        $changed = 0;
        foreach ($this->manager->all() as $schedule) {
            if ($schedule->mediaUrl !== $url) {
                continue;
            }
            $this->manager->save($schedule->withMediaUrl($replacementUrl, $this->clock->now()));
            ++$changed;
        }

        return $changed;
    }
}
