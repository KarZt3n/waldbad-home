<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Service;

use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\DirectDebit\DirectDebitBookingTransactionInterface;
use App\Logic\Membership\WorkAssignmentRefund\Manager\WorkAssignmentRefundRecordManagerInterface;
use App\Logic\Membership\WorkAssignmentRefund\Model\CreditTransferBatch;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundDraft;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;

/** Hält eine Sammelüberweisung in der Gutschrift-Historie fest — alle Haushalte gemeinsam oder gar nicht. */
readonly class WorkAssignmentRefundBookkeeper
{
    public function __construct(
        private WorkAssignmentRefundRecordManagerInterface $records,
        private IdentifierGeneratorInterface $identifierGenerator,
        private DirectDebitBookingTransactionInterface $transaction,
    ) {
    }

    /**
     * @param list<WorkAssignmentRefundDraft> $drafts
     */
    public function bookAll(array $drafts, CreditTransferBatch $batch): void
    {
        $this->transaction->execute(function () use ($drafts, $batch): void {
            foreach ($drafts as $draft) {
                $this->records->save(new WorkAssignmentRefundRecord(
                    id: $this->identifierGenerator->generate(),
                    primaryMemberNumber: $draft->primaryMemberNumber,
                    payerMemberId: $draft->recipient->id
                        ?? throw new \LogicException('Eine überwiesene Gutschrift hat immer einen Empfänger.'),
                    year: $draft->year,
                    amountCents: $draft->amountCents(),
                    messageId: $batch->messageId,
                    exportedAt: $batch->createdAt,
                ));
            }
        });
    }
}
