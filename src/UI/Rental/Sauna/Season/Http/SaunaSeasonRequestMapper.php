<?php

namespace App\UI\Rental\Sauna\Season\Http;

use App\Logic\Rental\Sauna\Season\Dto\CreateSaunaSeasonRequest;
use App\Logic\Rental\Sauna\Season\Dto\UpdateSaunaSeasonRequest;
use App\Logic\Rental\Sauna\Season\Model\SaunaClosure;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class SaunaSeasonRequestMapper
{
    private const int MAX_OPENING_HOURS = 28;
    private const int MAX_CLOSURES = 100;

    public function create(Request $request): CreateSaunaSeasonRequest
    {
        $data = $request->getPayload();

        return new CreateSaunaSeasonRequest(
            startsOn: $this->requiredDate($data, 'startsOn', 'Der Saisonbeginn'),
            endsOn: $this->optionalDate($data, 'endsOn', 'Das Saisonende'),
            slotDurationMinutes: $this->slotDuration($data),
            openingHours: $this->openingHours($data),
            closures: $this->closures($data),
        );
    }

    public function update(string $id, Request $request): UpdateSaunaSeasonRequest
    {
        $data = $request->getPayload();

        return new UpdateSaunaSeasonRequest(
            id: $id,
            startsOn: $this->requiredDate($data, 'startsOn', 'Der Saisonbeginn'),
            endsOn: $this->optionalDate($data, 'endsOn', 'Das Saisonende'),
            slotDurationMinutes: $this->slotDuration($data),
            openingHours: $this->openingHours($data),
            closures: $this->closures($data),
        );
    }

    /** @param InputBag<string|int|float|bool|null> $data */
    private function slotDuration(InputBag $data): int
    {
        $value = $data->get('slotDurationMinutes');
        if (!is_int($value)) {
            throw new BadRequestHttpException('Die Dauer einer Buchungseinheit muss als ganze Zahl angegeben werden.');
        }

        return $value;
    }

    /** @param InputBag<string|int|float|bool|null> $data */
    private function requiredDate(InputBag $data, string $key, string $label): \DateTimeImmutable
    {
        return $this->optionalDate($data, $key, $label)
            ?? throw new BadRequestHttpException(sprintf('%s ist erforderlich.', $label));
    }

    /** @param InputBag<string|int|float|bool|null> $data */
    private function optionalDate(InputBag $data, string $key, string $label): ?\DateTimeImmutable
    {
        $raw = trim($data->getString($key));
        if ($raw === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date->format('Y-m-d') !== $raw) {
            throw new BadRequestHttpException(sprintf('%s muss ein gültiges Datum im Format JJJJ-MM-TT sein.', $label));
        }

        return $date;
    }

    /**
     * Schließzeiten als `[{startsOn, endsOn?, reason?}]`; ohne `endsOn` gilt die Schließzeit nur für
     * den einen Tag.
     *
     * @param InputBag<string|int|float|bool|null> $data
     * @return list<SaunaClosure>
     */
    private function closures(InputBag $data): array
    {
        if (!$data->has('closures')) {
            return [];
        }
        $entries = $data->all('closures');
        if (!array_is_list($entries) || count($entries) > self::MAX_CLOSURES) {
            throw new BadRequestHttpException(sprintf('Die Schließzeiten müssen als Liste mit höchstens %d Einträgen angegeben werden.', self::MAX_CLOSURES));
        }

        $closures = [];
        foreach ($entries as $entry) {
            $rawStartsOn = is_array($entry) ? ($entry['startsOn'] ?? null) : null;
            $rawEndsOn = is_array($entry) ? ($entry['endsOn'] ?? null) : null;
            $reason = is_array($entry) ? ($entry['reason'] ?? '') : '';
            if (!is_string($rawStartsOn) || !(is_string($rawEndsOn) || $rawEndsOn === null) || !is_string($reason)) {
                throw new BadRequestHttpException('Jede Schließzeit benötigt einen Beginn als Datum; Ende und Grund sind optional.');
            }
            $startsOn = $this->dateValue($rawStartsOn, 'Der Beginn einer Schließzeit')
                ?? throw new BadRequestHttpException('Jede Schließzeit benötigt einen Beginn.');
            $closures[] = new SaunaClosure(
                startsOn: $startsOn,
                endsOn: $this->dateValue($rawEndsOn ?? '', 'Das Ende einer Schließzeit') ?? $startsOn,
                reason: trim($reason),
            );
        }

        return $closures;
    }

    private function dateValue(string $value, string $label): ?\DateTimeImmutable
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date->format('Y-m-d') !== $raw) {
            throw new BadRequestHttpException(sprintf('%s muss ein gültiges Datum im Format JJJJ-MM-TT sein.', $label));
        }

        return $date;
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     * @return list<SaunaOpeningHours>
     */
    private function openingHours(InputBag $data): array
    {
        $entries = $data->all('openingHours');
        if (!array_is_list($entries) || count($entries) > self::MAX_OPENING_HOURS) {
            throw new BadRequestHttpException('Die Sauna-Zeiten müssen als Liste mit höchstens 28 Einträgen angegeben werden.');
        }

        $openingHours = [];
        foreach ($entries as $entry) {
            $weekday = is_array($entry) ? ($entry['weekday'] ?? null) : null;
            $startTime = is_array($entry) ? ($entry['startTime'] ?? null) : null;
            $endTime = is_array($entry) ? ($entry['endTime'] ?? null) : null;
            if (!is_int($weekday) || !is_string($startTime) || !is_string($endTime)) {
                throw new BadRequestHttpException('Jede Sauna-Zeit benötigt Wochentag, Beginn und Ende.');
            }
            $parsedWeekday = Weekday::tryFrom($weekday)
                ?? throw new BadRequestHttpException('Der Wochentag einer Sauna-Zeit ist ungültig.');
            $openingHours[] = new SaunaOpeningHours($parsedWeekday, trim($startTime), trim($endTime));
        }

        return $openingHours;
    }
}
