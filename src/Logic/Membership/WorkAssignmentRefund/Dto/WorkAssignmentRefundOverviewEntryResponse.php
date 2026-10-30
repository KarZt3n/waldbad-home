<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Dto;

use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundDraft;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundObstacle;

readonly class WorkAssignmentRefundOverviewEntryResponse
{
    /**
     * `$memberNumber`/`$lastName`/`$firstName` beschreiben den Empfänger bzw. ohne eindeutigen
     * Empfänger das Hauptmitglied; `$recipientName` ist der Kontoinhaber für die Überweisung.
     *
     * @param list<array{kind: string, message: string}> $obstacles leer, wenn der Haushalt überwiesen werden kann
     */
    public function __construct(
        public string $primaryMemberNumber,
        public ?string $payerId,
        public string $memberNumber,
        public string $lastName,
        public string $firstName,
        public string $recipientName,
        public ?string $iban,
        public int $liableMemberCount,
        public int $workedMinutes,
        public int $requiredHoursPerAssignment,
        public int $creditCents,
        public int $refundedCents,
        public int $amountCents,
        public array $obstacles,
    ) {
    }

    public static function fromDraft(WorkAssignmentRefundDraft $draft): self
    {
        return new self(
            primaryMemberNumber: $draft->primaryMemberNumber,
            payerId: $draft->recipient?->id,
            memberNumber: $draft->contact->memberNumber,
            lastName: $draft->contact->lastName,
            firstName: $draft->contact->firstName,
            recipientName: $draft->recipientName,
            iban: $draft->recipient?->iban,
            liableMemberCount: $draft->credit->liableMemberCount,
            workedMinutes: $draft->credit->workedMinutes,
            requiredHoursPerAssignment: $draft->credit->requiredHoursPerAssignment,
            creditCents: $draft->credit->creditCents,
            refundedCents: $draft->refundedCents,
            amountCents: $draft->amountCents(),
            obstacles: array_map(
                static fn (WorkAssignmentRefundObstacle $obstacle): array => ['kind' => $obstacle->kind->value, 'message' => $obstacle->message],
                $draft->obstacles,
            ),
        );
    }
}
