<?php

namespace App\Logic\Membership\DirectDebit\Dto;

use App\Logic\Membership\DirectDebit\Model\DirectDebitPosition;
use App\Logic\Membership\DirectDebit\Model\PayerDirectDebitDraft;
use App\Logic\Membership\DirectDebit\Model\SequenceType;

readonly class PayerDirectDebitPreviewResponse
{
    /**
     * @param list<DirectDebitPositionResponse> $positions
     * @param list<string>                       $blockers
     * @param list<string>                       $warnings
     * @param list<array{value: string, label: string}> $sequenceTypes
     */
    public function __construct(
        public string $payerId,
        public string $payerMemberNumber,
        public string $payerName,
        public string $debtorName,
        public ?string $iban,
        public ?string $bankName,
        public ?string $mandateReference,
        public string $mandateSignedOn,
        public string $paymentInterval,
        public DirectDebitCreditorResponse $creditor,
        public array $positions,
        public array $blockers,
        public array $warnings,
        public string $defaultCollectionDate,
        public string $defaultSequenceType,
        public string $defaultRemittanceInformation,
        public array $sequenceTypes,
        public int $contributionYear,
        public bool $joiningYearDebit,
        public ?DirectDebitRecordResponse $lastDebit,
    ) {
    }

    public static function fromDraft(PayerDirectDebitDraft $draft): self
    {
        $payer = $draft->payer;

        return new self(
            payerId: $payer->id,
            payerMemberNumber: $payer->memberNumber,
            payerName: $payer->firstName.' '.$payer->lastName,
            debtorName: $draft->debtorName,
            iban: $payer->iban,
            bankName: $payer->bankName,
            mandateReference: $payer->mandateReference,
            mandateSignedOn: $draft->mandateSignedOn->format('Y-m-d'),
            paymentInterval: $payer->paymentInterval->value,
            creditor: DirectDebitCreditorResponse::fromCreditor($draft->creditor),
            positions: array_map(DirectDebitPositionResponse::fromPosition(...), $draft->positions),
            blockers: $draft->blockers,
            warnings: $draft->warnings,
            defaultCollectionDate: $draft->defaultCollectionDate->format('Y-m-d'),
            defaultSequenceType: $draft->defaultSequenceType->value,
            defaultRemittanceInformation: $draft->defaultRemittanceInformation,
            sequenceTypes: array_map(
                static fn (SequenceType $type): array => ['value' => $type->value, 'label' => $type->label()],
                SequenceType::cases(),
            ),
            contributionYear: $draft->contributionYear,
            joiningYearDebit: $draft->joiningYearDebit,
            lastDebit: $draft->lastRecord === null ? null : DirectDebitRecordResponse::fromRecord($draft->lastRecord),
        );
    }
}
