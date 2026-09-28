<?php

namespace App\Logic\Membership\DirectDebit;

use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;

interface DirectDebitRecordProcessorInterface
{
    public function save(DirectDebitRecord $record): DirectDebitRecord;
}
