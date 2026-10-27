<?php

namespace App\Logic\Rental\Sauna\Duty;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyCandidate;

/** Adapter zum Mitgliedermodul: Personensuche für den Sauna-Dienstplan. */
interface SaunaDutyMemberDirectoryInterface
{
    /** @return list<SaunaDutyCandidate> alle aktiven Treffer, alphabetisch nach Nachname und Vorname */
    public function search(string $term): array;

    public function find(string $memberId): ?SaunaDutyCandidate;
}
