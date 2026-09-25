<?php

namespace App\Data\Rental\Sauna\Season\Mapper;

use App\Data\Rental\Sauna\Season\Entity\SaunaSeasonEntity;
use App\Data\Rental\Sauna\Season\Entity\SaunaSeasonOpeningHoursEntity;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\Weekday;

readonly class SaunaSeasonMapper
{
    public function __construct(private IdentifierGeneratorInterface $identifierGenerator)
    {
    }

    public function toModel(SaunaSeasonEntity $entity): SaunaSeason
    {
        return new SaunaSeason(
            id: $entity->getId(),
            startsOn: $entity->getStartsOn(),
            endsOn: $entity->getEndsOn(),
            slotDurationMinutes: $entity->getSlotDurationMinutes(),
            openingHours: array_map(
                static fn (SaunaSeasonOpeningHoursEntity $hours): SaunaOpeningHours => new SaunaOpeningHours(
                    weekday: Weekday::from($hours->getWeekday()),
                    startTime: $hours->getStartTime(),
                    endTime: $hours->getEndTime(),
                ),
                $entity->getOpeningHours(),
            ),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            closedOn: $entity->getClosedOn(),
        );
    }

    public function createEntity(SaunaSeason $season): SaunaSeasonEntity
    {
        $entity = new SaunaSeasonEntity(
            id: $season->id,
            startsOn: $season->startsOn,
            endsOn: $season->endsOn,
            slotDurationMinutes: $season->slotDurationMinutes,
            createdAt: $season->createdAt,
            updatedAt: $season->updatedAt,
            closedOn: $season->closedOn,
        );
        $entity->replaceOpeningHours($this->openingHoursEntities($season, $entity));

        return $entity;
    }

    public function updateEntity(SaunaSeason $season, SaunaSeasonEntity $entity): void
    {
        $entity->update(
            startsOn: $season->startsOn,
            endsOn: $season->endsOn,
            slotDurationMinutes: $season->slotDurationMinutes,
            updatedAt: $season->updatedAt,
            closedOn: $season->closedOn,
        );
        $entity->replaceOpeningHours($this->openingHoursEntities($season, $entity));
    }

    /**
     * Die Zeitfenster werden bei jedem Speichern vollständig ersetzt und erhalten dabei neue
     * Kennungen — eine Wiederverwendung würde beim Flush mit den noch zu löschenden Zeilen
     * kollidieren (Doctrine führt Inserts vor Deletes aus).
     *
     * @return list<SaunaSeasonOpeningHoursEntity>
     */
    private function openingHoursEntities(SaunaSeason $season, SaunaSeasonEntity $entity): array
    {
        return array_map(
            fn (SaunaOpeningHours $hours, int $position): SaunaSeasonOpeningHoursEntity => new SaunaSeasonOpeningHoursEntity(
                id: $this->identifierGenerator->generate(),
                season: $entity,
                position: $position,
                weekday: $hours->weekday->value,
                startTime: $hours->startTime,
                endTime: $hours->endTime,
            ),
            $season->openingHours,
            array_keys($season->openingHours),
        );
    }
}
