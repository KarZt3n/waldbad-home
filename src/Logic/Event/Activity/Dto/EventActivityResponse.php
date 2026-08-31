<?php

namespace App\Logic\Event\Activity\Dto;

use App\Logic\Event\Activity\Model\EventActivity;

readonly class EventActivityResponse
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public bool $active,
        public ?int $defaultRequiredHelpers,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromActivity(EventActivity $activity): self
    {
        return new self(
            id: $activity->id,
            name: $activity->name,
            description: $activity->description,
            active: $activity->active,
            defaultRequiredHelpers: $activity->defaultRequiredHelpers,
            createdAt: $activity->createdAt,
            updatedAt: $activity->updatedAt,
        );
    }
}
