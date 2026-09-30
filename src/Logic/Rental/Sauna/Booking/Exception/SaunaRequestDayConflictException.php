<?php

namespace App\Logic\Rental\Sauna\Booking\Exception;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\Exception\ErrorDetailsInterface;

/**
 * Einzelne Wunschtage einer individuellen Anfrage sind nicht anfragbar (belegt, vergangen oder
 * untereinander überschneidend). `details()` nennt die betroffenen Tage (0-basiert, in der
 * Reihenfolge der Anfrage), damit die Oberfläche sie markieren kann.
 */
class SaunaRequestDayConflictException extends BusinessRuleViolationException implements ErrorDetailsInterface
{
    /**
     * @param non-empty-array<int, string> $problems Grund je betroffenem Wunschtag
     */
    public function __construct(private readonly array $problems, int $dayCount)
    {
        ksort($problems);
        parent::__construct(implode(' ', array_map(
            static fn (int $index, string $problem): string => $dayCount > 1 ? sprintf('Wunschtag %d: %s', $index + 1, $problem) : $problem,
            array_keys($problems),
            $problems,
        )));
    }

    public function details(): array
    {
        $days = array_keys($this->problems);
        sort($days);

        return ['days' => $days];
    }
}
