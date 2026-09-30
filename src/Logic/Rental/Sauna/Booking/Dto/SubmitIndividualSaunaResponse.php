<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

readonly class SubmitIndividualSaunaResponse
{
    /**
     * @param list<string> $bookingIds je Wunschtag eine Buchung
     */
    public function __construct(
        public string $requestId,
        public array $bookingIds,
    ) {
    }
}
