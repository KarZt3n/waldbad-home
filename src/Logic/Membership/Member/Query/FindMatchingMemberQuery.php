<?php

namespace App\Logic\Membership\Member\Query;

use App\Logic\Membership\Member\Dto\FindMatchingMemberRequest;
use App\Logic\Membership\Member\Dto\MatchedMemberResponse;
use App\Logic\Membership\Member\Service\MemberIdentityMatcher;

/**
 * Modulübergreifender Einstiegspunkt für das Zuordnen öffentlich eingereichter Personenangaben zu
 * einem Mitglied. Andere Module binden diese Query über ein eigenes Adapter-Interface an (siehe
 * z. B. `App\Data\Rental\Sauna\Booking\Adapter\MembershipSaunaGuestMatcher`) und erhalten nur die
 * für eine Verknüpfung nötigen Daten — nicht das vollständige Mitglieder-Model.
 */
readonly class FindMatchingMemberQuery
{
    public function __construct(private MemberIdentityMatcher $matcher)
    {
    }

    public function execute(FindMatchingMemberRequest $request): ?MatchedMemberResponse
    {
        $member = $this->matcher->match($request->firstName, $request->lastName, $request->birthDate, $request->email);

        return $member === null ? null : MatchedMemberResponse::fromMember($member);
    }
}
