<?php

namespace App\Logic\Membership\Member\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Member\Dto\MemberSearchResultResponse;
use App\Logic\Membership\Member\MemberProviderInterface;

/**
 * Modulübergreifender Einstiegspunkt für eine Personensuche nach Name oder Mitgliedsnummer, z. B.
 * zum Eintragen in den Sauna-Dienstplan. Ausgetretene Mitglieder werden nicht geliefert. Die
 * Treffer sind alphabetisch nach Nachname und Vorname sortiert.
 */
readonly class SearchActiveMembersQuery
{
    public function __construct(
        private MemberProviderInterface $provider,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<MemberSearchResultResponse> */
    public function execute(string $term, ?int $limit = null): array
    {
        if (trim($term) === '' || ($limit !== null && $limit < 1)) {
            return [];
        }
        $now = $this->clock->now();
        $results = [];
        foreach ($this->provider->search(trim($term)) as $member) {
            if ($member->isActive($now)) {
                $results[] = MemberSearchResultResponse::fromMember($member);
            }
        }

        return $limit === null ? $results : array_slice($results, 0, $limit);
    }
}
