<?php

namespace App\Logic\Rental\Sauna\Duty\Dto;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;

readonly class SaunaDutyResponse
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $date,
        public string $time,
        public string $memberId,
        public string $memberNumber,
        public string $firstName,
        public string $lastName,
    ) {
    }

    public static function fromDuty(SaunaDuty $duty): self
    {
        return new self($duty->id, $duty->date, $duty->time, $duty->memberId, $duty->memberNumber, $duty->firstName, $duty->lastName);
    }
}
