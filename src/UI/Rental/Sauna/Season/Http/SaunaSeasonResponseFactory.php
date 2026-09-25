<?php

namespace App\UI\Rental\Sauna\Season\Http;

use App\Logic\Rental\Sauna\Season\Dto\SaunaSeasonResponse;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;

readonly class SaunaSeasonResponseFactory
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     startsOn: string,
     *     endsOn: string|null,
     *     slotDurationMinutes: int,
     *     openingHours: list<array{weekday: int, startTime: string, endTime: string}>,
     *     createdAt: string,
     *     updatedAt: string,
     *     closedOn: string|null
     * }
     */
    public function season(SaunaSeasonResponse $season): array
    {
        return [
            'id' => $season->id,
            'name' => $season->name,
            'startsOn' => $season->startsOn->format('Y-m-d'),
            'endsOn' => $season->endsOn?->format('Y-m-d'),
            'slotDurationMinutes' => $season->slotDurationMinutes,
            'openingHours' => array_map(
                static fn (SaunaOpeningHours $hours): array => [
                    'weekday' => $hours->weekday->value,
                    'startTime' => $hours->startTime,
                    'endTime' => $hours->endTime,
                ],
                $season->openingHours,
            ),
            'createdAt' => $season->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $season->updatedAt->format(\DateTimeInterface::ATOM),
            'closedOn' => $season->closedOn?->format('Y-m-d'),
        ];
    }

    /**
     * @param list<SaunaSeasonResponse> $seasons
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function collection(array $seasons): array
    {
        return ['items' => array_map($this->season(...), $seasons), 'total' => count($seasons)];
    }
}
