<?php

namespace App\Data\Rental\Sauna\Duty\Mapper;

use App\Data\Rental\Sauna\Duty\Entity\SaunaDutyWaiverEntity;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;

readonly class SaunaDutyWaiverMapper
{
    public function toModel(SaunaDutyWaiverEntity $entity): SaunaDutyWaiver
    {
        return new SaunaDutyWaiver(
            id: $entity->getId(),
            date: $entity->getDate(),
            time: $entity->getTime(),
        );
    }

    public function createEntity(SaunaDutyWaiver $waiver): SaunaDutyWaiverEntity
    {
        return new SaunaDutyWaiverEntity(
            id: $waiver->id,
            date: $waiver->date->setTime(0, 0),
            time: $waiver->time,
        );
    }
}
