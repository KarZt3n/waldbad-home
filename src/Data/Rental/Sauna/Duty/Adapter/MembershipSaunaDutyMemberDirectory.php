<?php

namespace App\Data\Rental\Sauna\Duty\Adapter;

use App\Logic\Membership\Member\Dto\MemberSearchResultResponse;
use App\Logic\Membership\Member\Query\ListMemberContactsQuery;
use App\Logic\Membership\Member\Query\SearchActiveMembersQuery;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyCandidate;
use App\Logic\Rental\Sauna\Duty\SaunaDutyMemberDirectoryInterface;

/** Adapter auf die Such- und Kontakt-Queries der Mitgliederverwaltung (Vorbild: `MembershipSaunaGuestDirectory`). */
readonly class MembershipSaunaDutyMemberDirectory implements SaunaDutyMemberDirectoryInterface
{
    public function __construct(
        private SearchActiveMembersQuery $search,
        private ListMemberContactsQuery $contacts,
    ) {
    }

    public function search(string $term): array
    {
        return array_map(
            static fn (MemberSearchResultResponse $member): SaunaDutyCandidate => new SaunaDutyCandidate(
                memberId: $member->id,
                memberNumber: $member->memberNumber,
                firstName: $member->firstName,
                lastName: $member->lastName,
                street: $member->street,
                birthDate: $member->birthDate,
                boardMember: $member->boardMember,
            ),
            $this->search->execute($term),
        );
    }

    public function find(string $memberId): ?SaunaDutyCandidate
    {
        $contact = $this->contacts->execute([$memberId])[$memberId] ?? null;

        return $contact === null ? null : new SaunaDutyCandidate(
            memberId: $contact->id,
            memberNumber: $contact->memberNumber,
            firstName: $contact->firstName,
            lastName: $contact->lastName,
            street: $contact->street,
            birthDate: null,
            boardMember: false,
        );
    }
}
