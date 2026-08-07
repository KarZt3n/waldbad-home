<?php

namespace App\Logic\Event\HelpRequest\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\Dto\ParticipationIntervalInput;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\ParticipationInterval;
use App\Logic\Common\IdentifierGeneratorInterface;

readonly class RecordEventHelpParticipationUseCase
{
    public function __construct(
        private EventHelpRequestManagerInterface $manager,
        private ClockInterface $clock,
        private IdentifierGeneratorInterface $identifierGenerator,
    ) {
    }

    /**
     * @param list<ParticipationIntervalInput> $intervals
     */
    public function execute(
        string $id,
        bool $participated,
        array $intervals,
    ): EventHelpRequestResponse
    {
        $participationIntervals = array_map(
            fn (ParticipationIntervalInput $interval, int $position): ParticipationInterval => new ParticipationInterval(
                id: $this->identifierGenerator->generate(),
                position: $position,
                fromTime: $interval->fromTime,
                toTime: $interval->toTime,
            ),
            $intervals,
            array_keys($intervals),
        );
        $request = $this->manager->get($id)->recordParticipation(
            $participated,
            $participationIntervals,
            $this->clock->now(),
        );

        return EventHelpRequestResponse::fromRequest($this->manager->save($request));
    }
}
