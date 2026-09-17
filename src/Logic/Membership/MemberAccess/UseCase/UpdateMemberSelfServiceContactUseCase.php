<?php

namespace App\Logic\Membership\MemberAccess\UseCase;

use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\MemberAccess\Dto\MemberAccessSessionResponse;
use App\Logic\Membership\MemberAccess\Exception\InvalidMemberAccessTokenException;

/**
 * Aktualisiert Vor-/Nachname, Anschrift und Kontaktdaten eines über „Meine Mitgliedschaft“
 * erreichbaren Mitglieds (siehe `Member::withContactInfo()`) — wie bei `SendMemberMessageUseCase`
 * muss die aufgerufene Mitglieds-ID zu einem der über Token/Passwort erreichbaren Haushaltsmitglieder
 * gehören, sonst gilt derselbe Fehler wie bei einem ungültigen Token.
 *
 * `$applyAddressToHousehold`: überträgt Straße/PLZ/Ort zusätzlich auf jedes andere Mitglied
 * desselben Haushalts (`Member::withAddress()`, ohne deren Name/Kontaktdaten anzufassen) — für den
 * häufigen Fall eines gemeinsamen Umzugs der ganzen Familie.
 */
readonly class UpdateMemberSelfServiceContactUseCase
{
    public function __construct(
        private ResolveMemberAccessSessionUseCase $resolveSession,
        private MemberManagerInterface $members,
    ) {
    }

    public function execute(
        string $rawToken,
        string $password,
        string $memberId,
        string $firstName,
        string $lastName,
        string $street,
        string $postalCode,
        string $city,
        ?string $phone,
        ?string $email,
        bool $applyAddressToHousehold,
    ): MemberAccessSessionResponse {
        $session = $this->resolveSession->execute($rawToken, $password);
        $target = null;
        foreach ($session->members as $candidate) {
            if ($candidate->id === $memberId) {
                $target = $candidate;
                break;
            }
        }
        if ($target === null) {
            throw new InvalidMemberAccessTokenException();
        }

        $member = $this->members->get($memberId);
        $this->members->save($member->withContactInfo(
            firstName: trim($firstName),
            lastName: trim($lastName),
            street: trim($street),
            postalCode: trim($postalCode),
            city: trim($city),
            phone: $phone === null || trim($phone) === '' ? null : trim($phone),
            email: $email === null || trim($email) === '' ? null : trim($email),
        ));

        if ($applyAddressToHousehold) {
            foreach ($this->members->findByPrimaryMemberNumber($member->primaryMemberNumber) as $householdMember) {
                if ($householdMember->id === $member->id) {
                    continue;
                }
                $this->members->save($householdMember->withAddress(trim($street), trim($postalCode), trim($city)));
            }
        }

        return $this->resolveSession->execute($rawToken, $password);
    }
}
