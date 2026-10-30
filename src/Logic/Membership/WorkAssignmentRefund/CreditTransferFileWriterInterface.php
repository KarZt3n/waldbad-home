<?php

namespace App\Logic\Membership\WorkAssignmentRefund;

use App\Logic\Membership\WorkAssignmentRefund\Model\CreditTransferBatch;

/** Erzeugt die bei der Bank einzureichende SEPA-Überweisungsdatei (SEPA-XML). */
interface CreditTransferFileWriterInterface
{
    public function write(CreditTransferBatch $batch): string;
}
