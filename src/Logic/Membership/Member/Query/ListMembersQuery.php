<?php

namespace App\Logic\Membership\Member\Query;

use App\Logic\Membership\Member\Dto\MemberListEntryResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;

/**
 * Mitgliedertabelle der Verwaltung: nur die Angaben für Tabelle, Statusfilter und Zahler-Auswahl;
 * die vollständigen Daten lädt erst das Öffnen eines Datensatzes (`GetMemberQuery`).
 */
readonly class ListMembersQuery
{
    public function __construct(private MemberManagerInterface $manager)
    {
    }

    /**
     * @return list<MemberListEntryResponse>
     */
    public function execute(?string $searchTerm = null): array
    {
        return array_map(MemberListEntryResponse::fromEntry(...), $this->manager->searchListEntries($searchTerm));
    }
}
