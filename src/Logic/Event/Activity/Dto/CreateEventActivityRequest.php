<?php

namespace App\Logic\Event\Activity\Dto;

readonly class CreateEventActivityRequest
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $active,
        public ?int $defaultRequiredHelpers,
        public bool $alwaysIncluded,
    ) {
    }
}
