<?php

namespace App\Data\Rental\Sauna\Booking\Adapter;

use App\Logic\Membership\Member\Dto\FindMatchingMemberRequest;
use App\Logic\Membership\Member\Query\FindMatchingMemberQuery;
use App\Logic\Rental\Sauna\Booking\Model\SaunaGuestMatch;
use App\Logic\Rental\Sauna\Booking\SaunaGuestMatcherInterface;

/**
 * Bindet die Mitgliederverwaltung als externes System an die Vermietung an (Adapter-Prinzip,
 * `architektur.md` Abschnitt 2.4): Die Vermietung kennt nur `SaunaGuestMatcherInterface`, die
 * Mitgliederverwaltung stellt ihre öffentliche `FindMatchingMemberQuery` bereit.
 */
readonly class MembershipSaunaGuestMatcher implements SaunaGuestMatcherInterface
{
    public function __construct(private FindMatchingMemberQuery $query)
    {
    }

    public function match(string $firstName, string $lastName, \DateTimeImmutable $birthDate, ?string $email): ?SaunaGuestMatch
    {
        $member = $this->query->execute(new FindMatchingMemberRequest($firstName, $lastName, $birthDate, $email));

        return $member === null ? null : new SaunaGuestMatch($member->id, $member->memberNumber);
    }
}
