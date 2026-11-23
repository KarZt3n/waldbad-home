<?php

namespace App\Logic\Event\HelpRequest\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Service\ParticipationTimeRounding;

/**
 * Trägt eine anwesende Person per Klick aus – bis zur gemäß `ParticipationTimeRounding`
 * aufgerundeten aktuellen Uhrzeit.
 */
readonly class CheckOutEventHelperUseCase
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
        $current = $this->manager->get($id);
        $open = $current->openParticipationInterval();
        if ($open === null) {
            throw new BusinessRuleViolationException('Die Person ist nicht als anwesend markiert.');
        }
        $this->manager->save($current->checkOut(
            $this->rounding->checkOutTime($now, $open->fromTime),
            $this->identifierGenerator->generate(),
            $now,
        ));
    }
}
