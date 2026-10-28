<?php

namespace App\Data\Media\Library\Adapter;

use App\Logic\Event\Schedule\Dto\EventScheduleResponse;
use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Schedule\Query\ListEventSchedulesQuery;
use App\Logic\Event\Schedule\UseCase\ReplaceEventScheduleMediaReferenceUseCase;
use App\Logic\Media\Library\MediaUsageSourceInterface;
use App\Logic\Media\Library\Model\MediaUsage;
use App\Logic\Media\Library\Model\MediaUsageArea;

/** Adapter auf die Bilder der Veranstaltungen und Arbeitseinsätze. */
readonly class EventScheduleMediaUsageSource implements MediaUsageSourceInterface
{
    public function __construct(
        private ListEventSchedulesQuery $query,
        private ReplaceEventScheduleMediaReferenceUseCase $replaceUseCase,
    ) {
    }

    public function findUsages(): array
    {
        $usages = [];
        foreach ($this->query->execute() as $schedule) {
            if ($schedule->mediaUrl !== null) {
                $usages[] = $this->usage($schedule, $schedule->mediaUrl);
            }
        }

        return $usages;
    }

    public function replaceReferences(string $url, ?string $replacementUrl): int
    {
        return $this->replaceUseCase->execute($url, $replacementUrl);
    }

    private function usage(EventScheduleResponse $schedule, string $url): MediaUsage
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $schedule->date);

        return new MediaUsage(
            url: $url,
            area: MediaUsageArea::EventSchedule,
            referenceId: $schedule->id,
            title: $schedule->title,
            detail: sprintf(
                '%s am %s',
                $schedule->kind === EventScheduleKind::WorkAssignment ? 'Arbeitseinsatz' : 'Veranstaltung',
                $date === false ? $schedule->date : $date->format('d.m.Y'),
            ),
        );
    }
}
