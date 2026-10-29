<?php

namespace App\Logic\Rental\Sauna\Duty\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyPoint;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyWaiverResponse;
use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyWaiverManagerInterface;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyPointState;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;
use App\Logic\Rental\Sauna\Duty\Service\SaunaDutyRoster;

/**
 * Vermerkt für einen Dienstzeitpunkt (`$time`) oder den ganzen Tag (`$time === null`), dass kein Dienst
 * nötig ist. Ein Vermerk für den ganzen Tag ersetzt die einzelnen Vermerke des Tages, damit nach dem
 * Zurücknehmen nicht unerwartet alte Einzelvermerke wieder greifen. Eingetragene Dienste bleiben
 * bestehen — wer trotzdem kommen möchte, steht weiter im Plan.
 */
readonly class WaiveSaunaDutyUseCase
{
    public function __construct(
        private SaunaDutyRoster $roster,
        private SaunaDutyWaiverManagerInterface $waivers,
        private IdentifierGeneratorInterface $identifierGenerator,
    ) {
    }

    public function execute(\DateTimeImmutable $date, ?string $time): SaunaDutyWaiverResponse
    {
        $day = $this->roster->days($date, 1)[0];
        $scheduled = array_values(array_filter(
            $day->points,
            static fn (SaunaDutyPoint $point): bool => $point->state !== SaunaDutyPointState::Unscheduled,
        ));
        if ($day->closed || $scheduled === []) {
            throw new BusinessRuleViolationException(sprintf('Am %s ist kein Saunadienst vorgesehen.', $date->format('d.m.Y')));
        }
        if ($day->waiverId !== null) {
            throw new BusinessRuleViolationException(sprintf('Am %s ist bereits für den ganzen Tag kein Dienst nötig.', $date->format('d.m.Y')));
        }

        if ($time === null) {
            foreach ($scheduled as $point) {
                if ($point->waiverId !== null) {
                    $this->waivers->delete($point->waiverId);
                }
            }
        } else {
            $point = array_find($scheduled, static fn (SaunaDutyPoint $point): bool => $point->time === $time)
                ?? throw new BusinessRuleViolationException(sprintf('Am %s ist um %s Uhr kein Saunadienst vorgesehen.', $date->format('d.m.Y'), $time));
            if ($point->waiverId !== null) {
                throw new BusinessRuleViolationException(sprintf('Um %s Uhr ist bereits kein Dienst nötig.', $time));
            }
        }

        return SaunaDutyWaiverResponse::fromWaiver($this->waivers->save(new SaunaDutyWaiver(
            id: $this->identifierGenerator->generate(),
            date: $date->setTime(0, 0),
            time: $time,
        )));
    }
}
