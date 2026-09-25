<?php

namespace App\Data\Rental\Sauna\Booking\Adapter;

use App\Logic\Membership\Member\Dto\MemberContactResponse;
use App\Logic\Membership\Member\Query\ListMemberContactsQuery;
use App\Logic\Rental\Sauna\Booking\Model\SaunaGuestContact;
use App\Logic\Rental\Sauna\Booking\SaunaGuestDirectoryInterface;

/** Adapter auf `ListMemberContactsQuery` der Mitgliederverwaltung (siehe `MembershipSaunaGuestMatcher`). */
readonly class MembershipSaunaGuestDirectory implements SaunaGuestDirectoryInterface
{
    public function __construct(private ListMemberContactsQuery $query)
    {
    }

    public function contacts(array $memberIds): array
    {
        return array_map(
            static fn (MemberContactResponse $contact): SaunaGuestContact => new SaunaGuestContact(
                memberNumber: $contact->memberNumber,
                street: $contact->street,
                postalCode: $contact->postalCode,
                city: $contact->city,
                email: $contact->email,
            ),
            $this->query->execute($memberIds),
        );
    }
}
