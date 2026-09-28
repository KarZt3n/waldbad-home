<?php

namespace App\Logic\Membership\DirectDebit\Dto;

use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;

readonly class DirectDebitRecordResponse
{
    public function __construct(
        public int $contributionYear,
        public string $sequenceType,
        public string $collectionDate,
        public int $amountCents,
        public string $exportedAt,
        public bool $legacyImport,
    ) {
    }

    public static function fromRecord(DirectDebitRecord $record): self
    {
        return new self(
            contributionYear: $record->contributionYear,
            sequenceType: $record->sequenceType->value,
            collectionDate: $record->collectionDate->format('Y-m-d'),
            amountCents: $record->amountCents,
            exportedAt: $record->exportedAt->format(\DateTimeInterface::ATOM),
            legacyImport: $record->isLegacyImport(),
        );
    }
}
