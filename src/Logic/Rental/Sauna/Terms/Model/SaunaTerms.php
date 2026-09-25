<?php

namespace App\Logic\Rental\Sauna\Terms\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Konditionen der Sauna-Vermietung: Preis je Buchung (für die ganze Gruppe) bezogen auf eine
 * Bezugsdauer sowie die zulässige Gruppengröße. Eine Einzelnutzung ist ausgeschlossen, daher
 * beträgt die Mindestgröße immer mindestens zwei Personen.
 */
readonly class SaunaTerms
{
    public const int SMALLEST_GROUP = 2;
    public const int LARGEST_GROUP = 50;
    public const int MAX_PRICE_CENTS = 100_000;
    public const int MIN_PRICE_UNIT_MINUTES = 15;
    public const int MAX_PRICE_UNIT_MINUTES = 1440;

    public function __construct(
        public int $priceCents,
        public int $priceUnitMinutes,
        public int $minPersons,
        public int $maxPersons,
        public ?\DateTimeImmutable $updatedAt,
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
    }

    /** Ausgangswerte, solange die Redaktion noch keine Konditionen gespeichert hat. */
    public static function defaults(): self
    {
        return new self(priceCents: 2000, priceUnitMinutes: 120, minPersons: 2, maxPersons: 6, updatedAt: null);
    }

    public function revise(int $priceCents, int $priceUnitMinutes, int $minPersons, int $maxPersons, \DateTimeImmutable $updatedAt): self
    {
        return new self($priceCents, $priceUnitMinutes, $minPersons, $maxPersons, $updatedAt);
    }

    /** Anteiliger Preis für die gebuchte Dauer, kaufmännisch auf ganze Cent gerundet. */
    public function priceFor(int $durationMinutes): int
    {
        return (int) round($this->priceCents * $durationMinutes / $this->priceUnitMinutes);
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
