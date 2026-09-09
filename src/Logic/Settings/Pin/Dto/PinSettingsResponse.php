<?php

namespace App\Logic\Settings\Pin\Dto;

readonly class PinSettingsResponse
{
    /**
     * @param list<string> $protectedActions
     * @param list<string> $actionsWithOwnPin
     */
    public function __construct(
        public bool $globalPinIsSet,
        public array $protectedActions,
        public array $actionsWithOwnPin,
    ) {
    }
}
