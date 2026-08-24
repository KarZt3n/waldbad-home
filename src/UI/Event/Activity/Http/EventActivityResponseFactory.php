<?php

namespace App\UI\Event\Activity\Http;

use App\Logic\Event\Activity\Dto\EventActivityResponse;

readonly class EventActivityResponseFactory
{
    /** @return array<string, bool|string> */
    public function activity(EventActivityResponse $activity): array
    {
        return [
            'id' => $activity->id,
            'name' => $activity->name,
            'description' => $activity->description,
            'active' => $activity->active,
            'createdAt' => $activity->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $activity->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<EventActivityResponse> $activities
     * @return array{items: list<array<string, bool|string>>, total: int}
     */
    public function collection(array $activities): array
    {
        return ['items' => array_map($this->activity(...), $activities), 'total' => count($activities)];
    }
}
