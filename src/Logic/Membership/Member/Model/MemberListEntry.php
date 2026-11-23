<?php

namespace App\Logic\Membership\Member\Model;

/**
 * Die Angaben eines Mitglieds, die die Mitgliedertabelle und die Zahler-Auswahl brauchen — ohne
 * Bank-, Kontakt- und Beitragsdaten, die erst beim Öffnen eines Datensatzes geladen werden.
 */
readonly class MemberListEntry
{
    public function __construct(
        public string $id,
        public string $memberNumber,
        public string $primaryMemberNumber,
        public string $lastName,
        public string $firstName,
        public \DateTimeImmutable $birthDate,
        public string $street,
        public string $city,
        public ?\DateTimeImmutable $leftAt,
    ) {
    }

    /** Wie `Member::isActive()`: aktiv, solange das Austrittsdatum nicht erreicht ist. */
    public function isActive(\DateTimeImmutable $at): bool
    {
        return $this->leftAt === null || $this->leftAt > $at;
    }
}
