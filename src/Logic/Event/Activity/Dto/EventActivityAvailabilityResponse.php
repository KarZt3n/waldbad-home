<?php

namespace App\Logic\Event\Activity\Dto;

readonly class EventActivityAvailabilityResponse
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public int $requiredHelpers,
        public int $registeredHelpers,
        public ?string $time = null,
        public ?string $meetTime = null,
        public ?string $meetPlace = null,
        public ?string $remark = null,
    ) {
    }
}
