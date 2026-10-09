<?php

namespace App\Logic\Rental\Sauna\Duty;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyCandidate;

/** Adapter zum Mitgliedermodul: Personensuche für den Sauna-Dienstplan. */
interface SaunaDutyMemberDirectoryInterface
{
    /** @return list<SaunaDutyCandidate> nur aktive Mitglieder */
    public function search(string $term, int $limit): array;

    public function find(string $memberId): ?SaunaDutyCandidate;
}
