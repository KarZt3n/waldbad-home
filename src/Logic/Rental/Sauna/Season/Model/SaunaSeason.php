<?php

namespace App\Logic\Rental\Sauna\Season\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Zeitraum, in dem die Sauna buchbar ist, samt Wochenplan. Das Ende ist optional — eine Saison
 * ohne Ende gilt bis auf Weiteres. Unabhängig vom geplanten Ende kann eine Saison abgeschlossen
 * werden (`$closedOn`): ab diesem Tag ist sie nicht mehr buchbar, das Enddatum bleibt unverändert.
 * Es gibt höchstens eine nicht abgeschlossene Saison (siehe `SaunaSeasonRotation`). Die Wochenzeitfenster werden in gleich lange Buchungseinheiten
 * (`$slotDurationMinutes`) zerlegt, die im öffentlichen Kalender als frei/belegt erscheinen.
 */
readonly class SaunaSeason
{
    public const int MIN_SLOT_MINUTES = 15;
    public const int MAX_SLOT_MINUTES = 720;

    /**
     * @param list<SaunaOpeningHours> $openingHours
     */
    public function __construct(
        public string $id,
        public \DateTimeImmutable $startsOn,
        public ?\DateTimeImmutable $endsOn,
        public int $slotDurationMinutes,
        public array $openingHours,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $closedOn = null,
    ) {
        if ($this->endsOn !== null && $this->endsOn->format('Y-m-d') < $this->startsOn->format('Y-m-d')) {
            throw new BusinessRuleViolationException('Das Saisonende darf nicht vor dem Saisonbeginn liegen.');
        }
        if ($this->slotDurationMinutes < self::MIN_SLOT_MINUTES || $this->slotDurationMinutes > self::MAX_SLOT_MINUTES) {
            throw new BusinessRuleViolationException(sprintf(
                'Die Dauer einer Buchungseinheit muss zwischen %d und %d Minuten liegen.',
                self::MIN_SLOT_MINUTES,
                self::MAX_SLOT_MINUTES,
            ));
        }
        if ($this->openingHours === []) {
            throw new BusinessRuleViolationException('Für die Saison muss mindestens ein Wochentag mit Zeiten hinterlegt sein.');
        }
        foreach ($this->openingHours as $index => $hours) {
            if ($hours->endMinute() - $hours->startMinute() < $this->slotDurationMinutes) {
                throw new BusinessRuleViolationException('Jedes Zeitfenster muss mindestens eine vollständige Buchungseinheit umfassen.');
            }
            foreach (array_slice($this->openingHours, $index + 1) as $other) {
                if ($hours->overlaps($other)) {
                    throw new BusinessRuleViolationException('Die Zeitfenster eines Wochentags dürfen sich nicht überschneiden.');
                }
            }
        }
    }

    /**
     * @param list<SaunaOpeningHours> $openingHours
     */
    public function revise(
        \DateTimeImmutable $startsOn,
        ?\DateTimeImmutable $endsOn,
        int $slotDurationMinutes,
        array $openingHours,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $this->id,
            startsOn: $startsOn,
            endsOn: $endsOn,
            slotDurationMinutes: $slotDurationMinutes,
            openingHours: $openingHours,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            closedOn: $this->closedOn,
        );
    }

    public function isClosed(): bool
    {
        return $this->closedOn !== null;
    }

    /** Schließt die Saison zum Tag `$closedOn` ab; ab diesem Tag ist sie nicht mehr buchbar. */
    public function close(\DateTimeImmutable $closedOn, \DateTimeImmutable $updatedAt): self
    {
        if ($this->isClosed()) {
            throw new BusinessRuleViolationException('Die Saison wurde bereits abgeschlossen.');
        }

        return new self(
            id: $this->id,
            startsOn: $this->startsOn,
            endsOn: $this->endsOn,
            slotDurationMinutes: $this->slotDurationMinutes,
            openingHours: $this->openingHours,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            closedOn: $closedOn->setTime(0, 0),
        );
    }

    /** Beginnt nach `$date` und wird vor ihrem Beginn weder beendet noch abgeschlossen. */
    public function isUpcoming(\DateTimeImmutable $date): bool
    {
        return $this->startsOn->format('Y-m-d') > $date->format('Y-m-d') && $this->covers($this->startsOn);
    }

    public function covers(\DateTimeImmutable $date): bool
    {
        $day = $date->format('Y-m-d');

        return $day >= $this->startsOn->format('Y-m-d')
            && ($this->endsOn === null || $day <= $this->endsOn->format('Y-m-d'))
            && ($this->closedOn === null || $day < $this->closedOn->format('Y-m-d'));
    }

    /**
     * @return list<SaunaTimeSlot> chronologisch sortiert; leer, wenn der Tag nicht zur Saison gehört
     */
    public function slotsOn(\DateTimeImmutable $date): array
    {
        if (!$this->covers($date)) {
            return [];
        }

        $day = $date->setTime(0, 0);
        $weekday = Weekday::fromDate($day);
        $windows = array_values(array_filter(
            $this->openingHours,
            static fn (SaunaOpeningHours $hours): bool => $hours->weekday === $weekday,
        ));
        usort($windows, static fn (SaunaOpeningHours $left, SaunaOpeningHours $right): int => $left->startMinute() <=> $right->startMinute());

        $slots = [];
        foreach ($windows as $window) {
            for ($start = $window->startMinute(); $start + $this->slotDurationMinutes <= $window->endMinute(); $start += $this->slotDurationMinutes) {
                $slots[] = new SaunaTimeSlot(
                    $day,
                    SaunaOpeningHours::fromMinutes($start),
                    SaunaOpeningHours::fromMinutes($start + $this->slotDurationMinutes),
                );
            }
        }

        return $slots;
    }

    /**
     * Prüft, ob `$startTime`–`$endTime` an `$date` genau eine lückenlose Folge von
     * Buchungseinheiten innerhalb eines einzigen Zeitfensters ist.
     */
    public function isBookableRange(\DateTimeImmutable $date, string $startTime, string $endTime): bool
    {
        $slots = $this->slotsOn($date);
        $startIndex = null;
        foreach ($slots as $index => $slot) {
            if ($slot->startTime === $startTime) {
                $startIndex = $index;
                break;
            }
        }
        if ($startIndex === null) {
            return false;
        }

        for ($index = $startIndex; $index < count($slots); ++$index) {
            if ($index > $startIndex && $slots[$index]->startTime !== $slots[$index - 1]->endTime) {
                return false;
            }
            if ($slots[$index]->endTime === $endTime) {
                return true;
            }
        }

        return false;
    }
}
