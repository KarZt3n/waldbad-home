<?php

namespace App\Data\Rental\Sauna\Duty\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sauna_duty_waiver')]
#[ORM\Index(name: 'idx_sauna_duty_waiver_date', columns: ['waiver_date'])]
class SaunaDutyWaiverEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(name: 'waiver_date', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $date,
        /** `null` = ganzer Tag (siehe `SaunaDutyWaiver::$time`). */
        #[ORM\Column(name: 'waiver_time', type: Types::STRING, length: 5, nullable: true)]
        private ?string $time,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getTime(): ?string { return $this->time; }
}
