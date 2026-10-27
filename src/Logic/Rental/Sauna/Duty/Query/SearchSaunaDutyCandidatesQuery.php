<?php

namespace App\Logic\Rental\Sauna\Duty\Query;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyCandidate;
use App\Logic\Rental\Sauna\Duty\SaunaDutyMemberDirectoryInterface;

/**
 * Personensuche für den Dienstplan; Vorstandsmitglieder stehen immer vorn, da sie den Dienst
 * hauptsächlich übernehmen. Gekürzt wird erst nach dem Sortieren, damit kein Vorstandsmitglied
 * hinter alphabetisch früheren Treffern herausfällt.
 */
readonly class SearchSaunaDutyCandidatesQuery
{
    public const int LIMIT = 20;

    public function __construct(private SaunaDutyMemberDirectoryInterface $directory)
    {
    }

    /** @return list<SaunaDutyCandidate> */
    public function execute(string $term): array
    {
        $candidates = $this->directory->search($term);
        // usort ist stabil: Innerhalb beider Gruppen bleibt die alphabetische Reihenfolge des Verzeichnisses
        // erhalten, die (anders als ein Stringvergleich in PHP) Umlaute korrekt einsortiert.
        usort($candidates, static fn (SaunaDutyCandidate $left, SaunaDutyCandidate $right): int => $right->boardMember <=> $left->boardMember);

        return array_slice($candidates, 0, self::LIMIT);
    }
}
