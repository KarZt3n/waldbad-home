<?php

namespace App\Logic\Membership\Application\Service;

use App\Logic\Membership\Application\Model\Applicant;
use App\Logic\Membership\Application\Model\ApplicationMemberMatch;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;

/**
 * Findet zu den Personen eines Mitgliedsantrags bereits bestehende, aktive Mitglieder. Bewusst
 * strenger als `MemberIdentityMatcher`: Das Geburtsdatum muss immer übereinstimmen, dazu Vorname,
 * Nachname oder E-Mail-Adresse — so wird ein Tippfehler im Namen oder ein geänderter Nachname
 * erkannt, eine bloße Namensgleichheit aber nicht. Bleiben mehrere Kandidaten, entscheidet der
 * vollständige Name; sonst gilt die Person als nicht gefunden.
 */
readonly class ApplicationMemberMatcher
{
    public function __construct(private MemberManagerInterface $members)
    {
    }

    public function match(MembershipApplication $application, \DateTimeImmutable $at): ?ApplicationMemberMatch
    {
        $matched = [];
        foreach ($application->applicants as $applicant) {
            $member = $this->matchApplicant($applicant, $at);
            if ($member !== null) {
                $matched[$applicant->position] = $member;
            }
        }
        if ($matched === []) {
            return null;
        }
        ksort($matched);

        $primaryMemberNumber = $matched[array_key_first($matched)]->primaryMemberNumber;
        $conflicting = [];
        foreach ($matched as $member) {
            if ($member->primaryMemberNumber !== $primaryMemberNumber) {
                $conflicting[$member->primaryMemberNumber] = $member->primaryMemberNumber;
            }
        }

        $matchedInHousehold = array_filter($matched, static fn (Member $member): bool => $member->primaryMemberNumber === $primaryMemberNumber);
        $matchedIds = array_map(static fn (Member $member): string => $member->id, $matchedInHousehold);

        return new ApplicationMemberMatch(
            primaryMemberNumber: $primaryMemberNumber,
            matchedMembers: $matchedInHousehold,
            bankAccountMember: $this->bankAccountMember($application, $matched, $primaryMemberNumber),
            conflictingMemberNumbers: array_values($conflicting),
            missingMembers: array_values(array_filter(
                $this->members->findByPrimaryMemberNumber($primaryMemberNumber),
                static fn (Member $member): bool => $member->isActive($at) && !in_array($member->id, $matchedIds, true),
            )),
        );
    }

    private function matchApplicant(Applicant $applicant, \DateTimeImmutable $at): ?Member
    {
        $firstName = self::normalize($applicant->firstName);
        $lastName = self::normalize($applicant->lastName);
        $email = $applicant->email === null ? null : self::normalize($applicant->email);
        $birthDate = $applicant->birthDate->format('Y-m-d');

        $candidates = [];
        $pool = [
            ...$this->members->search($applicant->lastName),
            ...$this->members->search($applicant->firstName),
            ...($applicant->email === null ? [] : $this->members->findByEmail($applicant->email)),
        ];
        foreach ($pool as $member) {
            if (!$member->isActive($at) || $member->birthDate->format('Y-m-d') !== $birthDate) {
                continue;
            }
            if (self::normalize($member->firstName) === $firstName
                || self::normalize($member->lastName) === $lastName
                || ($email !== null && $member->email !== null && self::normalize($member->email) === $email)
            ) {
                $candidates[$member->id] = $member;
            }
        }
        if (count($candidates) === 1) {
            return array_values($candidates)[0];
        }
        $byFullName = array_values(array_filter(
            $candidates,
            static fn (Member $member): bool => self::normalize($member->firstName) === $firstName && self::normalize($member->lastName) === $lastName,
        ));

        return count($byFullName) === 1 ? $byFullName[0] : null;
    }

    /**
     * Die Bankverbindung des Antrags gehört zu dem Mitglied, das für die erste Person zahlt: das
     * gefundene Mitglied selbst, wenn es Selbstzahler ist, sonst sein Zahler. Ist die erste Person
     * neu, das zahlende Mitglied des Haushalts (Zahler des Hauptmitglieds).
     *
     * @param array<int, Member> $matched
     */
    private function bankAccountMember(MembershipApplication $application, array $matched, string $primaryMemberNumber): ?Member
    {
        $reference = $matched[$application->applicants[0]->position] ?? $this->members->findByMemberNumber($primaryMemberNumber);
        if ($reference === null || $reference->primaryMemberNumber !== $primaryMemberNumber) {
            return null;
        }
        if ($reference->payerType === PayerType::SelfPayer) {
            return $reference;
        }

        return $reference->payerMemberId === null ? null : $this->members->get($reference->payerMemberId);
    }

    public static function normalize(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }
}
