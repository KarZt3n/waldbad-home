<?php

namespace App\Logic\Rental\Sauna\Booking\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Dto\GetSaunaCalendarRequest;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaCalendarResponse;
use App\Logic\Rental\Sauna\Booking\Service\SaunaSlotAvailability;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Terms\Dto\SaunaTermsResponse;
use App\Logic\Rental\Sauna\Terms\Manager\SaunaTermsManagerInterface;

readonly class GetSaunaCalendarQuery
{
    public const int MAX_DAYS = 42;

    public function __construct(
        private SaunaSlotAvailability $availability,
        private SaunaTermsManagerInterface $terms,
        private SaunaSeasonManagerInterface $seasons,
        private ClockInterface $clock,
    ) {
    }

    public function execute(GetSaunaCalendarRequest $request): SaunaCalendarResponse
    {
        if ($request->days < 1 || $request->days > self::MAX_DAYS) {
            throw new BusinessRuleViolationException(sprintf('Es können 1 bis %d Tage abgefragt werden.', self::MAX_DAYS));
        }
        $from = $request->from->setTime(0, 0);
        $now = $this->clock->now();
        $season = $this->seasons->findCurrentOrUpcoming($now);

        return new SaunaCalendarResponse(
            from: $from,
            to: $from->modify(sprintf('+%d days', $request->days - 1)),
            days: $this->availability->calendar($from, $request->days, $now),
            terms: SaunaTermsResponse::fromTerms($this->terms->current()),
            seasonStartsOn: $season?->startsOn,
            seasonEndsOn: $season?->endsOn,
        );
    }
}
