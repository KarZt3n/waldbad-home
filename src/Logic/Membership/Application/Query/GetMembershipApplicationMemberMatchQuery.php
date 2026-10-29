<?php

namespace App\Logic\Membership\Application\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Iban;
use App\Logic\Membership\Application\Dto\ApplicantMemberMatchResponse;
use App\Logic\Membership\Application\Dto\MemberMatchFieldResponse;
use App\Logic\Membership\Application\Dto\MembershipApplicationMemberMatchResponse;
use App\Logic\Membership\Application\Dto\MissingHouseholdMemberResponse;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\Applicant;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Application\Service\ApplicationMemberMatcher;
use App\Logic\Membership\Member\Model\Member;

/**
 * Hinweis „Bereits Mitglied“ beim Mitgliedsantrag: welche Personen schon Mitglied sind und je Feld,
 * ob der Antrag denselben Wert enthält, einen neuen oder keinen (dann bleibt der bestehende), sowie
 * welche bisherigen Mitglieder des Haushalts im Antrag fehlen.
 * `null`, wenn keine Person des Antrags als Mitglied gefunden wurde.
 */
readonly class GetMembershipApplicationMemberMatchQuery
{
    public function __construct(
        private MembershipApplicationManagerInterface $applications,
        private ApplicationMemberMatcher $matcher,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $applicationId): ?MembershipApplicationMemberMatchResponse
    {
        return $this->forApplication($this->applications->get($applicationId));
    }

    public function forApplication(MembershipApplication $application): ?MembershipApplicationMemberMatchResponse
    {
        $match = $this->matcher->match($application, $this->clock->now());
        if ($match === null) {
            return null;
        }
        $bank = $match->bankAccountMember;

        return new MembershipApplicationMemberMatchResponse(
            primaryMemberNumber: $match->primaryMemberNumber,
            applicants: array_map(
                fn (Applicant $applicant): ApplicantMemberMatchResponse => $this->applicant($applicant, $match->matchedMembers[$applicant->position] ?? null),
                $application->applicants,
            ),
            bankAccountMemberNumber: $bank?->memberNumber,
            bankAccountFields: $bank === null ? [] : [
                self::field('accountHolder', 'Kontoinhaber', $application->accountHolder, $bank->accountHolder),
                self::field('iban', 'IBAN', Iban::normalize($application->iban), $bank->iban === null ? null : Iban::normalize($bank->iban), compareNormalized: false),
                self::field('bankName', 'Bank', $application->bankName, $bank->bankName),
            ],
            conflictingMemberNumbers: $match->conflictingMemberNumbers,
            missingMembers: array_map(static fn (Member $member): MissingHouseholdMemberResponse => new MissingHouseholdMemberResponse(
                memberId: $member->id,
                memberNumber: $member->memberNumber,
                name: trim($member->firstName.' '.$member->lastName),
                birthDate: $member->birthDate->format('Y-m-d'),
                familyRole: $member->familyRole->value,
                removalBlocker: $match->removalBlocker($member),
            ), $match->missingMembers),
        );
    }

    private function applicant(Applicant $applicant, ?Member $member): ApplicantMemberMatchResponse
    {
        $values = [
            ['salutation', 'Anrede', $applicant->salutation->value, $member?->salutation->value],
            ['firstName', 'Vorname', $applicant->firstName, $member?->firstName],
            ['lastName', 'Nachname', $applicant->lastName, $member?->lastName],
            ['birthDate', 'Geburtsdatum', $applicant->birthDate->format('Y-m-d'), $member?->birthDate->format('Y-m-d')],
            ['street', 'Straße', trim($applicant->street.' '.$applicant->houseNumber), $member?->street],
            ['postalCode', 'Postleitzahl', $applicant->postalCode, $member?->postalCode],
            ['city', 'Ort', $applicant->city, $member?->city],
            ['phone', 'Telefon', $applicant->phone, $member?->phone],
            ['email', 'E-Mail-Adresse', $applicant->email, $member?->email],
        ];

        return new ApplicantMemberMatchResponse(
            position: $applicant->position,
            applicantName: trim($applicant->firstName.' '.$applicant->lastName),
            memberId: $member?->id,
            memberNumber: $member?->memberNumber,
            memberName: $member === null ? null : trim($member->firstName.' '.$member->lastName),
            fields: array_map(
                static fn (array $value): MemberMatchFieldResponse => $member === null
                    ? new MemberMatchFieldResponse($value[0], $value[1], $value[2], null, MemberMatchFieldResponse::NEW)
                    : self::field($value[0], $value[1], $value[2], $value[3]),
                $values,
            ),
        );
    }

    private static function field(string $key, string $label, ?string $applicationValue, ?string $memberValue, bool $compareNormalized = true): MemberMatchFieldResponse
    {
        $status = match (true) {
            $applicationValue === null || trim($applicationValue) === '' => MemberMatchFieldResponse::KEPT,
            $memberValue !== null && ($compareNormalized
                ? ApplicationMemberMatcher::normalize($applicationValue) === ApplicationMemberMatcher::normalize($memberValue)
                : $applicationValue === $memberValue) => MemberMatchFieldResponse::IDENTICAL,
            default => MemberMatchFieldResponse::CHANGED,
        };

        return new MemberMatchFieldResponse($key, $label, $applicationValue, $memberValue, $status);
    }
}
