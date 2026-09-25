<?php

namespace App\Data\Rental\Sauna\Terms\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Einzeiliger Datensatz (fester Schlüssel `SaunaTermsEntity::SINGLETON_ID`) mit den Sauna-Konditionen. */
#[ORM\Entity]
#[ORM\Table(name: 'sauna_terms')]
class SaunaTermsEntity
{
    public const string SINGLETON_ID = 'default';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::INTEGER)]
        private int $priceCents,
        #[ORM\Column(type: Types::INTEGER)]
        private int $priceUnitMinutes,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $minPersons,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $maxPersons,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getPriceCents(): int { return $this->priceCents; }
    public function getPriceUnitMinutes(): int { return $this->priceUnitMinutes; }
    public function getMinPersons(): int { return $this->minPersons; }
    public function getMaxPersons(): int { return $this->maxPersons; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function update(int $priceCents, int $priceUnitMinutes, int $minPersons, int $maxPersons, \DateTimeImmutable $updatedAt): void
    {
        $this->priceCents = $priceCents;
        $this->priceUnitMinutes = $priceUnitMinutes;
        $this->minPersons = $minPersons;
        $this->maxPersons = $maxPersons;
        $this->updatedAt = $updatedAt;
    }
}
