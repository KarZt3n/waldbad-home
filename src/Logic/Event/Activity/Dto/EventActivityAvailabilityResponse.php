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
    ) {
    }
}
