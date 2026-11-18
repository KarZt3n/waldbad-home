<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Model;

use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\MemberAccess\Dto\WorkAssignmentCreditResponse;

/**
 * Die für einen Zahler-Strang ermittelte Gutschrift eines Beitragsjahres (siehe
 * `WorkAssignmentRefundPlanner`). `$payerMemberNumber` kennzeichnet den Strang; Empfänger ist der
 * Zahler — `$recipient` ist null, wenn kein gültiger Zahler hinterlegt ist (dann steht der Grund in
 * `$obstacles`). `$contact` ist das in Listen angezeigte Mitglied — der Zahler oder, ohne Zahler, das
 * Mitglied selbst; `$primaryMemberNumber` dessen Haushalt. `$refundedCents` wurde für dieses Jahr
 * bereits an den Zahler überwiesen.
 */
readonly class WorkAssignmentRefundDraft
{
    /**
     * @param non-empty-list<Member>              $members
     * @param list<WorkAssignmentRefundObstacle> $obstacles
     */
    public function __construct(
        public string $payerMemberNumber,
        public string $primaryMemberNumber,
        public int $year,
        public array $members,
        public ?Member $recipient,
        public Member $contact,
        public string $recipientName,
        public WorkAssignmentCreditResponse $credit,
        public int $refundedCents,
        public array $obstacles,
    ) {
    }

    /** Noch zu überweisender Betrag: die Gutschrift abzüglich bereits Überwiesenem. */
    public function amountCents(): int
    {
        return max(0, $this->credit->creditCents - $this->refundedCents);
    }

    public function isExportable(): bool
    {
        return $this->amountCents() > 0 && $this->obstacles === [] && $this->recipient !== null;
    }
}
