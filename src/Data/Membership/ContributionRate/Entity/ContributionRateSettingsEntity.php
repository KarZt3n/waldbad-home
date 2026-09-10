<?php

namespace App\Data\Membership\ContributionRate\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Einzeilige Singleton-Tabelle (siehe `ContributionRateSettings`). Die feste Id `self::ID`
 * verhindert, dass versehentlich mehrere Zeilen entstehen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'contribution_rate_settings')]
class ContributionRateSettingsEntity
{
    public const string ID = 'default';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $id,
        #[ORM\Column(name: 'valid_from', type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $validFrom,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function update(?\DateTimeImmutable $validFrom): void
    {
        $this->validFrom = $validFrom;
    }
}
