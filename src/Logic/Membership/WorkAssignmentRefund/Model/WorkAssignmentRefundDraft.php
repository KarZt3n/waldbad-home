<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Model;

use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\MemberAccess\Dto\WorkAssignmentCreditResponse;

/**
 * Die für einen Haushalt ermittelte Gutschrift eines Beitragsjahres (siehe
 * `WorkAssignmentRefundPlanner`). Empfänger ist der Zahler, der den Arbeitseinsatz-Zuschlag des
 * Haushalts trägt; `$recipient` ist null, wenn er sich nicht eindeutig bestimmen lässt (dann steht
 * der Grund in `$obstacles`). `$contact` ist das in Listen angezeigte Mitglied — der Empfänger oder,
 * ohne Empfänger, das Hauptmitglied. `$refundedCents` wurde für dieses Jahr bereits überwiesen.
 */
readonly class WorkAssignmentRefundDraft
{
    /**
     * @param non-empty-list<Member>              $householdMembers
     * @param list<WorkAssignmentRefundObstacle> $obstacles
     */
    public function __construct(
        public string $primaryMemberNumber,
        public int $year,
        public array $householdMembers,
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
