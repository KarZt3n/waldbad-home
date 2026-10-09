<?php

namespace App\Data\Rental\Sauna\Duty\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sauna_duty')]
#[ORM\Index(name: 'idx_sauna_duty_date_time', columns: ['duty_date', 'duty_time'])]
#[ORM\UniqueConstraint(name: 'uniq_sauna_duty_member', columns: ['duty_date', 'duty_time', 'member_id'])]
class SaunaDutyEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(name: 'duty_date', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $date,
        #[ORM\Column(name: 'duty_time', type: Types::STRING, length: 5)]
        private string $time,
        /** Verweis auf `MemberEntity::$id`, ohne DB-Fremdschlüssel (siehe `SaunaDuty::$memberId`). */
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $memberId,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $memberNumber,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $firstName,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $lastName,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getTime(): string { return $this->time; }
    public function getMemberId(): string { return $this->memberId; }
    public function getMemberNumber(): string { return $this->memberNumber; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
}
