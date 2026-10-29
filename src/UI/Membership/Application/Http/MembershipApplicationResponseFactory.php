<?php

namespace App\UI\Membership\Application\Http;

use App\Logic\Membership\Application\Dto\ApplicantMemberMatchResponse;
use App\Logic\Membership\Application\Dto\MemberMatchFieldResponse;
use App\Logic\Membership\Application\Dto\MembershipApplicationMemberMatchResponse;
use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Dto\MissingHouseholdMemberResponse;
use App\Logic\Membership\Application\Model\Applicant;

readonly class MembershipApplicationResponseFactory
{
    /**
     * @param list<MembershipApplicationResponse> $applications
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function collection(array $applications): array
    {
        return [
            'items' => array_map($this->application(...), $applications),
            'total' => count($applications),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function application(MembershipApplicationResponse $application): array
    {
        return [
            'id' => $application->id,
            'membershipType' => $application->membershipType->value,
            'applicants' => array_map($this->applicant(...), $application->applicants),
            'accountHolder' => $application->accountHolder,
            'iban' => $application->iban,
            'bankName' => $application->bankName,
            'signerName' => $application->signerName,
            'emailConsent' => $application->emailConsent,
            'declarationVersion' => $application->declarationVersion,
            'version' => $application->version,
            'submittedAt' => $application->submittedAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $application->updatedAt->format(\DateTimeInterface::ATOM),
            'releasedAt' => $application->releasedAt?->format(\DateTimeInterface::ATOM),
            'releasedMemberIds' => $application->releasedMemberIds,
            'rejectedAt' => $application->rejectedAt?->format(\DateTimeInterface::ATOM),
            'rejectionReason' => $application->rejectionReason,
            'acceptedDeclarations' => $application->acceptedDeclarations,
            'mergedIntoMemberNumber' => $application->mergedIntoMemberNumber,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function memberMatch(MembershipApplicationMemberMatchResponse $match): array
    {
        return [
            'primaryMemberNumber' => $match->primaryMemberNumber,
            'applicants' => array_map(fn (ApplicantMemberMatchResponse $applicant): array => [
                'position' => $applicant->position,
                'applicantName' => $applicant->applicantName,
                'memberId' => $applicant->memberId,
                'memberNumber' => $applicant->memberNumber,
                'memberName' => $applicant->memberName,
                'fields' => array_map($this->matchField(...), $applicant->fields),
            ], $match->applicants),
            'bankAccountMemberNumber' => $match->bankAccountMemberNumber,
            'bankAccountFields' => array_map($this->matchField(...), $match->bankAccountFields),
            'conflictingMemberNumbers' => $match->conflictingMemberNumbers,
            'missingMembers' => array_map(static fn (MissingHouseholdMemberResponse $member): array => [
                'memberId' => $member->memberId,
                'memberNumber' => $member->memberNumber,
                'name' => $member->name,
                'birthDate' => $member->birthDate,
                'familyRole' => $member->familyRole,
                'removalBlocker' => $member->removalBlocker,
            ], $match->missingMembers),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function matchField(MemberMatchFieldResponse $field): array
    {
        return [
            'key' => $field->key,
            'label' => $field->label,
            'applicationValue' => $field->applicationValue,
            'memberValue' => $field->memberValue,
            'status' => $field->status,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function applicant(Applicant $applicant): array
    {
        return [
            'id' => $applicant->id,
            'position' => $applicant->position,
            'salutation' => $applicant->salutation->value,
            'firstName' => $applicant->firstName,
            'lastName' => $applicant->lastName,
            'birthDate' => $applicant->birthDate->format('Y-m-d'),
            'street' => $applicant->street,
            'houseNumber' => $applicant->houseNumber,
            'postalCode' => $applicant->postalCode,
            'city' => $applicant->city,
            'phone' => $applicant->phone,
            'email' => $applicant->email,
        ];
    }

}
