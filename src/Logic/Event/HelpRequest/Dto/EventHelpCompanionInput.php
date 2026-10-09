<?php

namespace App\Logic\Event\HelpRequest\Dto;

/**
 * Eine über „Ich bringe jemanden mit“ zusätzlich angemeldete Person; sie wird als eigene
 * Helferanmeldung gespeichert.
 */
readonly class EventHelpCompanionInput
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public \DateTimeImmutable $birthDate,
    ) {
    }
}
