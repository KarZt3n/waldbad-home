<?php

namespace App\Logic\Membership\DirectDebit;

use App\Logic\Membership\DirectDebit\Model\DirectDebitBatch;

/**
 * Erzeugt die bei der Bank einzureichende Lastschriftdatei (SEPA-XML) — je Batch ein Zahlungsblock
 * (eigenes Fälligkeitsdatum/eigene Sequenz), alle mit demselben Gläubiger. Nachrichtenkennung und
 * Erstellzeitpunkt der Datei stammen aus dem ersten Batch.
 */
interface DirectDebitFileWriterInterface
{
    public function write(DirectDebitBatch $batch, DirectDebitBatch ...$furtherBatches): string;
}
