<?php

namespace App\Data\Membership\Member\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Einzeiliger Zähler zur Vergabe fortlaufender Mitgliedsnummern (siehe
 * `DoctrineMemberNumberGenerator`). Es gibt bewusst kein zugehöriges Business-Model in der Logic
 * Layer — die Sequenz ist reine Persistenz-Infrastruktur ohne eigene Geschäftslogik. Die Entity
 * existiert trotzdem, damit Tools, die das Schema aus den Entity-Mappings ableiten (z. B.
 * Doctrines `SchemaTool` in Tests), die Tabelle mit erzeugen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'member_number_sequence')]
class MemberNumberSequenceEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::INTEGER)]
        private int $id,
        #[ORM\Column(type: Types::INTEGER)]
        private int $nextValue,
    ) {
    }

    public function getId(): int { return $this->id; }
    public function getNextValue(): int { return $this->nextValue; }
}
