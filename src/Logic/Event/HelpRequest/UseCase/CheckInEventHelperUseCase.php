<?php

namespace App\Logic\Event\HelpRequest\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Service\ParticipationTimeRounding;

/**
 * Markiert eine angemeldete Person per Klick als anwesend – ab der gemäß
 * `ParticipationTimeRounding` abgerundeten aktuellen Uhrzeit.
 */
readonly class CheckInEventHelperUseCase
{
    public function __construct(
        private EventHelpRequestManagerInterface $manager,
        private ParticipationTimeRounding $rounding,
        private ClockInterface $clock,
        private IdentifierGeneratorInterface $identifierGenerator,
    ) {
    }

    public function execute(string $id): void
    {
        $now = $this->clock->now();
        $request = $this->manager->get($id)->checkIn(
            $this->rounding->checkInTime($now),
            $this->identifierGenerator->generate(),
            $now,
        );
        $this->manager->save($request);
    }
}
