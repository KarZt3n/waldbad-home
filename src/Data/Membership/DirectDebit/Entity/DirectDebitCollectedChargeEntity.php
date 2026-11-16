<?php

namespace App\Data\Membership\DirectDebit\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Per Lastschrift eingezogene Einmalgebühr (`member_contribution_charge.id`). Wie die
 * Lastschrift-Historie bewusst ohne Fremdschlüssel; `record_id` ist bei Gebühren leer, die bei der
 * Umstellung auf getrennte Exporte pauschal als eingezogen übernommen wurden.
 */
#[ORM\Entity]
#[ORM\Table(name: 'direct_debit_collected_charge')]
class DirectDebitCollectedChargeEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'charge_id', type: Types::STRING, length: 36)]
        private string $chargeId,
        #[ORM\Column(name: 'record_id', type: Types::STRING, length: 36, nullable: true)]
        private ?string $recordId,
        #[ORM\Column(name: 'collected_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $collectedAt,
    ) {
    }

    public function getChargeId(): string { return $this->chargeId; }
    public function getRecordId(): ?string { return $this->recordId; }
    public function getCollectedAt(): \DateTimeImmutable { return $this->collectedAt; }
}
