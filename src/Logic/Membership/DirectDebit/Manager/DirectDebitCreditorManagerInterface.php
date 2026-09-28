<?php

namespace App\Logic\Membership\DirectDebit\Manager;

use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;

interface DirectDebitCreditorManagerInterface
{
    public function get(): DirectDebitCreditor;

    public function save(DirectDebitCreditor $creditor): DirectDebitCreditor;
}
