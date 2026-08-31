<?php

namespace App\Logic\Event\Activity\Dto;

readonly class UpdateEventActivityRequest
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public bool $active,
        public ?int $defaultRequiredHelpers,
        public bool $alwaysIncluded,
    ) {
    }
}
