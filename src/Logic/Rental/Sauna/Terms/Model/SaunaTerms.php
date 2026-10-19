<?php

namespace App\Logic\Rental\Sauna\Terms\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Konditionen der Sauna-Vermietung: Preis je Buchung (für die ganze Gruppe) bezogen auf eine
 * Bezugsdauer, die zulässige Gruppengröße und der Vorlauf in Tagen. Die Bezugsdauer ist zugleich
 * die feste Dauer jeder Buchung — kürzer oder länger ist nicht anfragbar. Eine Einzelnutzung ist
 * ausgeschlossen, daher beträgt die Mindestgröße immer mindestens zwei Personen.
 */
readonly class SaunaTerms
{
    public const int SMALLEST_GROUP = 2;
    public const int LARGEST_GROUP = 50;
    public const int MAX_PRICE_CENTS = 100_000;
    public const int MIN_PRICE_UNIT_MINUTES = 15;
    public const int MAX_PRICE_UNIT_MINUTES = 1440;
    public const int MAX_LEAD_DAYS = 60;
    public const int DEFAULT_LEAD_DAYS = 2;

    public function __construct(
        public int $priceCents,
        public int $priceUnitMinutes,
        public int $minPersons,
        public int $maxPersons,
        public ?\DateTimeImmutable $updatedAt,
        public int $leadDays,
    ) {
        if ($this->priceCents < 0 || $this->priceCents > self::MAX_PRICE_CENTS) {
            throw new BusinessRuleViolationException('Der Preis muss zwischen 0 und 1.000 Euro liegen.');
        }
        if ($this->priceUnitMinutes < self::MIN_PRICE_UNIT_MINUTES || $this->priceUnitMinutes > self::MAX_PRICE_UNIT_MINUTES) {
            throw new BusinessRuleViolationException('Die Bezugsdauer des Preises muss zwischen 15 Minuten und 24 Stunden liegen.');
        }
        if ($this->minPersons < self::SMALLEST_GROUP) {
            throw new BusinessRuleViolationException('Eine Einzelnutzung ist ausgeschlossen: Die Mindestgröße einer Gruppe beträgt mindestens zwei Personen.');
        }
        if ($this->maxPersons < $this->minPersons || $this->maxPersons > self::LARGEST_GROUP) {
            throw new BusinessRuleViolationException('Die Höchstgröße einer Gruppe muss zwischen der Mindestgröße und 50 Personen liegen.');
        }
        if ($this->leadDays < 0 || $this->leadDays > self::MAX_LEAD_DAYS) {
            throw new BusinessRuleViolationException(sprintf('Der Vorlauf muss zwischen 0 und %d Tagen liegen.', self::MAX_LEAD_DAYS));
        }
    }

    /** Ausgangswerte, solange die Redaktion noch keine Konditionen gespeichert hat. */
    public static function defaults(): self
    {
        return new self(priceCents: 2000, priceUnitMinutes: 120, minPersons: 2, maxPersons: 6, updatedAt: null, leadDays: self::DEFAULT_LEAD_DAYS);
    }

    public function revise(int $priceCents, int $priceUnitMinutes, int $minPersons, int $maxPersons, int $leadDays, \DateTimeImmutable $updatedAt): self
    {
        return new self($priceCents, $priceUnitMinutes, $minPersons, $maxPersons, $updatedAt, $leadDays);
    }

    /** Anteiliger Preis für die gebuchte Dauer, kaufmännisch auf ganze Cent gerundet. */
    public function priceFor(int $durationMinutes): int
    {
        return (int) round($this->priceCents * $durationMinutes / $this->priceUnitMinutes);
    }

    /** Jede Buchung umfasst genau die Bezugsdauer des Preises (`$priceUnitMinutes`). */
    public function assertBookingDuration(int $durationMinutes): void
    {
        if ($durationMinutes !== $this->priceUnitMinutes) {
            throw new BusinessRuleViolationException(sprintf('Eine Sauna-Buchung dauert immer genau %s.', $this->bookingDurationLabel()));
        }
    }

    /** Erster Tag, für den ab `$now` noch angefragt werden kann: heute zuzüglich Vorlauf. */
    public function earliestBookableDay(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->setTime(0, 0)->modify(sprintf('+%d days', $this->leadDays));
    }

    public function assertLeadTime(\DateTimeImmutable $date, \DateTimeImmutable $now): void
    {
        $earliest = $this->earliestBookableDay($now);
        if ($date->format('Y-m-d') < $earliest->format('Y-m-d')) {
            throw new BusinessRuleViolationException(sprintf(
                'Die Sauna kann nur mit %s Vorlauf angefragt werden, frühestens für den %s.',
                $this->leadDays === 1 ? '1 Tag' : sprintf('%d Tagen', $this->leadDays),
                $earliest->format('d.m.Y'),
            ));
        }
    }

    private function bookingDurationLabel(): string
    {
        if ($this->priceUnitMinutes % 60 !== 0) {
            return sprintf('%d Minuten', $this->priceUnitMinutes);
        }
        $hours = intdiv($this->priceUnitMinutes, 60);

        return $hours === 1 ? '1 Stunde' : sprintf('%d Stunden', $hours);
    }

    public function assertGroupSize(int $persons): void
    {
        if ($persons < $this->minPersons || $persons > $this->maxPersons) {
            throw new BusinessRuleViolationException(sprintf(
                'Die Sauna kann nur von Gruppen mit %d bis %d Personen gebucht werden.',
                $this->minPersons,
                $this->maxPersons,
            ));
        }
    }
}
