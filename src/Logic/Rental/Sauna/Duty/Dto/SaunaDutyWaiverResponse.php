<?php

namespace App\Logic\Rental\Sauna\Duty\Dto;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;

readonly class SaunaDutyWaiverResponse
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $date,
        public ?string $time,
    ) {
    }

    public static function fromWaiver(SaunaDutyWaiver $waiver): self
    {
        return new self($waiver->id, $waiver->date, $waiver->time);
    }
}
