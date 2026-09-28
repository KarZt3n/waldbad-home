<?php

namespace App\Logic\Membership\DirectDebit;

use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;

interface DirectDebitCreditorProviderInterface
{
    /** Liefert immer einen Datensatz, auch wenn noch nie gespeichert wurde (dann leer). */
    public function get(): DirectDebitCreditor;
}
