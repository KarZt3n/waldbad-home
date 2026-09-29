<?php

namespace App\Logic\Membership\DirectDebit\Dto;

use App\Logic\Membership\DirectDebit\Model\DirectDebitObstacle;
use App\Logic\Membership\DirectDebit\Model\PayerDirectDebitDraft;

readonly class DirectDebitOverviewEntryResponse
{
    /**
     * @param list<array{kind: string, message: string}> $obstacles leer, wenn der Zahler im Sammelexport enthalten ist
     */
    public function __construct(
        public string $payerId,
        public string $memberNumber,
        public string $primaryMemberNumber,
        public string $lastName,
        public string $firstName,
        public ?string $iban,
        public int $amountCents,
        public string $collectionDate,
        public string $sequenceType,
        public array $obstacles,
    ) {
    }

    public static function fromDraft(PayerDirectDebitDraft $draft): self
    {
        $payer = $draft->payer;

        return new self(
            payerId: $payer->id,
            memberNumber: $payer->memberNumber,
            primaryMemberNumber: $payer->primaryMemberNumber,
            lastName: $payer->lastName,
            firstName: $payer->firstName,
            iban: $payer->iban,
            amountCents: $draft->defaultAmountCents(),
            collectionDate: $draft->defaultCollectionDate->format('Y-m-d'),
            sequenceType: $draft->defaultSequenceType->value,
            obstacles: array_map(
                static fn (DirectDebitObstacle $obstacle): array => ['kind' => $obstacle->kind->value, 'message' => $obstacle->message],
                $draft->bulkExportObstacles(),
            ),
        );
    }
}
