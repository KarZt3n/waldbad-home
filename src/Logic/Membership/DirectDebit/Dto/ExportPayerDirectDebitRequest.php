<?php

namespace App\Logic\Membership\DirectDebit\Dto;

use App\Logic\Membership\DirectDebit\Model\SequenceType;

readonly class ExportPayerDirectDebitRequest
{
    /**
     * @param list<string> $positionIds die in der Vorschau ausgewählten Positionen (`DirectDebitPosition::$id`)
     */
    public function __construct(
        public string $memberId,
        public \DateTimeImmutable $collectionDate,
        public SequenceType $sequenceType,
        public string $remittanceInformation,
        public array $positionIds,
    ) {
    }
}
