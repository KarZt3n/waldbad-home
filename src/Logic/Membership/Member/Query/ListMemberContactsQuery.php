<?php

namespace App\Logic\Membership\Member\Query;

use App\Logic\Membership\Member\Dto\MemberContactResponse;
use App\Logic\Membership\Member\MemberProviderInterface;

/**
 * Modulübergreifender Einstiegspunkt für die Kontaktdaten verknüpfter Mitglieder (Nummer, Name,
 * Anschrift, E-Mail) — z. B. zur Anzeige von Sauna-Anmeldungen. Liefert bewusst keine Bank- oder
 * Beitragsdaten; nicht (mehr) vorhandene Mitglieder werden übersprungen.
 */
readonly class ListMemberContactsQuery
{
    public function __construct(private MemberProviderInterface $provider)
    {
    }

    /**
     * @param list<string> $memberIds
     * @return array<string, MemberContactResponse> nach Mitglieds-ID
     */
    public function execute(array $memberIds): array
    {
        $contacts = [];
        foreach (array_unique($memberIds) as $memberId) {
            $member = $this->provider->find($memberId);
            if ($member !== null) {
                $contacts[$member->id] = MemberContactResponse::fromMember($member);
            }
        }

        return $contacts;
    }
}
