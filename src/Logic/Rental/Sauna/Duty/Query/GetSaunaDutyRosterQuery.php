<?php

namespace App\Logic\Rental\Sauna\Duty\Query;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyRosterDay;
use App\Logic\Rental\Sauna\Duty\Service\SaunaDutyRoster;

readonly class GetSaunaDutyRosterQuery
{
    public const int MAX_DAYS = 42;

    public function __construct(private SaunaDutyRoster $roster)
    {
    }

    /** @return list<SaunaDutyRosterDay> */
    public function execute(\DateTimeImmutable $from, int $days): array
    {
        if ($days < 1 || $days > self::MAX_DAYS) {
            throw new BusinessRuleViolationException(sprintf('Es können 1 bis %d Tage abgefragt werden.', self::MAX_DAYS));
        }

        return $this->roster->days($from, $days);
    }
}
