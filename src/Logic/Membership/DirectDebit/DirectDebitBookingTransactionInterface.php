<?php

namespace App\Logic\Membership\DirectDebit;

/** Führt die Buchung eines Lastschrift-Exports (Historie + nächste Buchung) atomar aus. */
interface DirectDebitBookingTransactionInterface
{
    /** @param callable(): void $work */
    public function execute(callable $work): void;
}
