<?php

namespace App\Data\Membership\DirectDebit\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Einzeilige Singleton-Tabelle (siehe `DirectDebitCreditor`). Die feste Id `self::ID` verhindert,
 * dass versehentlich mehrere Zeilen entstehen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'direct_debit_creditor')]
class DirectDebitCreditorEntity
{
    public const string ID = 'default';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 70, nullable: true)]
        private ?string $name,
        #[ORM\Column(name: 'creditor_id', type: Types::STRING, length: 35, nullable: true)]
        private ?string $creditorId,
        #[ORM\Column(type: Types::STRING, length: 34, nullable: true)]
        private ?string $iban,
        #[ORM\Column(type: Types::STRING, length: 11, nullable: true)]
        private ?string $bic,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCreditorId(): ?string
    {
        return $this->creditorId;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function update(?string $name, ?string $creditorId, ?string $iban, ?string $bic): void
    {
        $this->name = $name;
        $this->creditorId = $creditorId;
        $this->iban = $iban;
        $this->bic = $bic;
    }
}
