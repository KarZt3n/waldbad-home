<?php

namespace App\UI\Membership\Application\Http;

use App\Logic\Membership\Application\Dto\MembershipApplicationResponse;
use App\Logic\Membership\Application\Model\Applicant;

readonly class MembershipApplicationResponseFactory
{
    /**
     * @param list<MembershipApplicationResponse> $applications
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function collection(array $applications, bool $includeSensitive = false): array
    {
        return [
            'items' => array_map(fn (MembershipApplicationResponse $application): array => $this->application($application, $includeSensitive), $applications),
            'total' => count($applications),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function application(MembershipApplicationResponse $application, bool $includeSensitive = false): array
    {
        return [
            'id' => $application->id,
            'membershipType' => $application->membershipType->value,
            'applicants' => array_map($this->applicant(...), $application->applicants),
            'accountHolder' => $application->accountHolder,
            'iban' => $includeSensitive ? $application->iban : $this->maskedIban($application->iban),
            'bankName' => $application->bankName,
            'signerName' => $application->signerName,
            'emailConsent' => $application->emailConsent,
            'declarationVersion' => $application->declarationVersion,
            'status' => $application->status->value,
            'externalReference' => $application->externalReference,
            'failureReason' => $application->failureReason,
            'version' => $application->version,
            'submittedAt' => $application->submittedAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $application->updatedAt->format(\DateTimeInterface::ATOM),
            'processingAt' => $application->processingAt?->format(\DateTimeInterface::ATOM),
            'completedAt' => $application->completedAt?->format(\DateTimeInterface::ATOM),
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

    private function maskedIban(string $iban): string
    {
        return substr($iban, 0, 4).str_repeat('•', max(4, strlen($iban) - 8)).substr($iban, -4);
    }
}
