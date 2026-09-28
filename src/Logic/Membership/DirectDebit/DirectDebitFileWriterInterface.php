<?php

namespace App\Logic\Membership\DirectDebit;

use App\Logic\Membership\DirectDebit\Model\DirectDebitBatch;

/** Erzeugt die bei der Bank einzureichende Lastschriftdatei (SEPA-XML) aus einem Batch. */
interface DirectDebitFileWriterInterface
{
    public function write(DirectDebitBatch $batch): string;
}
