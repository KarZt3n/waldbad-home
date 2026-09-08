<?php

namespace App\Logic\Membership\Member\Dto;

readonly class ImportMembersRequest
{
    /**
     * @param list<CreateMemberRequest> $rows Jede Zeile trägt bereits eine feste (importierte) Mitgliedsnummer;
     *                                         ob daraus ein neues Mitglied entsteht oder ein bestehendes
     *                                         aktualisiert wird, entscheidet der UseCase anhand dieser Nummer.
     */
    public function __construct(
        public array $rows,
    ) {
    }
}
