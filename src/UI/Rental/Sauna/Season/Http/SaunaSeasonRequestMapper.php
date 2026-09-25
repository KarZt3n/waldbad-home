<?php

namespace App\UI\Rental\Sauna\Season\Http;

use App\Logic\Rental\Sauna\Season\Dto\CreateSaunaSeasonRequest;
use App\Logic\Rental\Sauna\Season\Dto\UpdateSaunaSeasonRequest;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class SaunaSeasonRequestMapper
{
    private const int MAX_OPENING_HOURS = 28;

    public function create(Request $request): CreateSaunaSeasonRequest
    {
        $data = $request->getPayload();

        return new CreateSaunaSeasonRequest(
            name: $this->name($data),
            startsOn: $this->requiredDate($data, 'startsOn', 'Der Saisonbeginn'),
            endsOn: $this->optionalDate($data, 'endsOn', 'Das Saisonende'),
            slotDurationMinutes: $this->slotDuration($data),
            openingHours: $this->openingHours($data),
        );
    }

    public function update(string $id, Request $request): UpdateSaunaSeasonRequest
    {
        $data = $request->getPayload();

        return new UpdateSaunaSeasonRequest(
            id: $id,
            name: $this->name($data),
            startsOn: $this->requiredDate($data, 'startsOn', 'Der Saisonbeginn'),
            endsOn: $this->optionalDate($data, 'endsOn', 'Das Saisonende'),
            slotDurationMinutes: $this->slotDuration($data),
            openingHours: $this->openingHours($data),
        );
    }

    /** @param InputBag<string|int|float|bool|null> $data */
    private function name(InputBag $data): string
    {
        $name = trim($data->getString('name'));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new BadRequestHttpException('Die Saison benötigt eine Bezeichnung mit höchstens 120 Zeichen.');
        }

        return $name;
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
