<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Service;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitCreditorManagerInterface;
use App\Logic\Membership\WorkAssignmentRefund\CreditTransferFileWriterInterface;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundFileResponse;
use App\Logic\Membership\WorkAssignmentRefund\Model\CreditTransferBatch;
use App\Logic\Membership\WorkAssignmentRefund\Model\CreditTransferTransaction;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundDraft;

/**
 * Erzeugt aus überweisbaren Gutschriften die SEPA-Überweisungsdatei und hält sie in der
 * Gutschrift-Historie fest — gemeinsam genutzt von Sammel- und Einzelexport.
 */
readonly class WorkAssignmentRefundExporter
{
    public function __construct(
        private CreditTransferFileWriterInterface $writer,
        private WorkAssignmentRefundBookkeeper $bookkeeper,
        private DirectDebitCreditorManagerInterface $creditors,
    ) {
    }

    /**
     * @param non-empty-list<WorkAssignmentRefundDraft> $drafts nur überweisbare (`isExportable()`)
     */
    public function export(array $drafts, \DateTimeImmutable $executionDate, \DateTimeImmutable $now, string $fileNameSuffix): WorkAssignmentRefundFileResponse
    {
        if ($executionDate->format('Y-m-d') < $now->format('Y-m-d')) {
            throw new BusinessRuleViolationException('Das Ausführungsdatum darf nicht in der Vergangenheit liegen.');
        }

        $transactions = [];
        foreach ($drafts as $draft) {
            $recipient = $draft->recipient;
            if (!$draft->isExportable() || $recipient === null || $recipient->iban === null) {
                throw new \LogicException('Nur überweisbare Gutschriften dürfen exportiert werden.');
            }
            $transactions[] = new CreditTransferTransaction(
                endToEndId: sprintf('AE%d-%s-%s', $draft->year, $draft->primaryMemberNumber, $now->format('Ymd')),
                amountCents: $draft->amountCents(),
                creditorName: $draft->recipientName,
                creditorIban: $recipient->iban,
                remittanceInformation: sprintf(
                    'Gutschrift Arbeitseinsatz %d, Mitgl.-Nr. %s, %s Std.',
                    $draft->year,
                    $draft->primaryMemberNumber,
                    rtrim(rtrim(number_format($draft->credit->workedMinutes / 60, 2, ',', ''), '0'), ','),
                ),
            );
        }

        $batch = new CreditTransferBatch(
            messageId: sprintf('WB-AE-%s', $now->format('YmdHis')),
            createdAt: $now,
            debtor: $this->creditors->get(),
            executionDate: $executionDate,
            transactions: $transactions,
        );
        $content = $this->writer->write($batch);
        $this->bookkeeper->bookAll($drafts, $batch);

        return new WorkAssignmentRefundFileResponse(
            fileName: sprintf('sepa-gutschrift-arbeitseinsatz-%d-%s-%s.xml', $drafts[0]->year, $fileNameSuffix, $now->format('Ymd-His')),
            content: $content,
        );
    }
}
