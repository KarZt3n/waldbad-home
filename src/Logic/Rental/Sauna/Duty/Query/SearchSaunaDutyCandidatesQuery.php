<?php

namespace App\Logic\Rental\Sauna\Duty\Query;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyCandidate;
use App\Logic\Rental\Sauna\Duty\SaunaDutyMemberDirectoryInterface;

/** Personensuche für den Dienstplan; Vorstandsmitglieder stehen vorn, da sie den Dienst hauptsächlich übernehmen. */
readonly class SearchSaunaDutyCandidatesQuery
{
    public const int LIMIT = 20;

    public function __construct(private SaunaDutyMemberDirectoryInterface $directory)
    {
    }

    /** @return list<SaunaDutyCandidate> */
    public function execute(string $term): array
    {
        $candidates = $this->directory->search($term, self::LIMIT);
        usort($candidates, static fn (SaunaDutyCandidate $left, SaunaDutyCandidate $right): int => $right->boardMember <=> $left->boardMember);

        return $candidates;
    }
}
