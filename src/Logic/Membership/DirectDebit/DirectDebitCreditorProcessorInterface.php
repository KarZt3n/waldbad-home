<?php

namespace App\Logic\Membership\DirectDebit;

use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;

interface DirectDebitCreditorProcessorInterface
{
    public function save(DirectDebitCreditor $creditor): DirectDebitCreditor;
}
