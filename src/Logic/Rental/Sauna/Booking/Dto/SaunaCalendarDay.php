<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

readonly class SaunaCalendarDay
{
    /**
     * @param list<SaunaCalendarSlot> $slots leer an geschlossenen Tagen
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public array $slots,
        /** Der Tag fällt in eine Schließzeit der Saison. */
        public bool $closed = false,
        public string $closedReason = '',
    ) {
    }
}
