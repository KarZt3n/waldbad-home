<?php

namespace App\Data\Rental\Sauna\Duty\Mapper;

use App\Data\Rental\Sauna\Duty\Entity\SaunaDutyEntity;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;

readonly class SaunaDutyMapper
{
    public function toModel(SaunaDutyEntity $entity): SaunaDuty
    {
        return new SaunaDuty(
            id: $entity->getId(),
            date: $entity->getDate(),
            time: $entity->getTime(),
            memberId: $entity->getMemberId(),
            memberNumber: $entity->getMemberNumber(),
            firstName: $entity->getFirstName(),
            lastName: $entity->getLastName(),
        );
    }

    public function createEntity(SaunaDuty $duty): SaunaDutyEntity
    {
        return new SaunaDutyEntity(
            id: $duty->id,
            date: $duty->date->setTime(0, 0),
            time: $duty->time,
            memberId: $duty->memberId,
            memberNumber: $duty->memberNumber,
            firstName: $duty->firstName,
            lastName: $duty->lastName,
        );
    }
}
