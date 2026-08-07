<?php

namespace App\Logic\Membership\Application\Dto;

use App\Logic\Membership\Application\Model\Applicant;
use App\Logic\Membership\Application\Model\ApplicationStatus;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\Application\Model\MembershipType;

readonly class MembershipApplicationResponse
{
    /**
     * @param list<Applicant> $applicants
     */
    public function __construct(
        public string $id,
        public MembershipType $membershipType,
        public array $applicants,
        public string $accountHolder,
        public string $iban,
        public ?string $bankName,
        public string $signerName,
        public bool $emailConsent,
        public string $declarationVersion,
        public ApplicationStatus $status,
        public ?string $externalReference,
        public ?string $failureReason,
        public int $version,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $processingAt,
        public ?\DateTimeImmutable $completedAt,
    ) {
    }

    public static function fromApplication(MembershipApplication $application): self
    {
        return new self(
            id: $application->id,
            membershipType: $application->membershipType,
            applicants: $application->applicants,
            accountHolder: $application->accountHolder,
            iban: $application->iban,
            bankName: $application->bankName,
            signerName: $application->signerName,
            emailConsent: $application->emailConsent,
            declarationVersion: $application->declarationVersion,
            status: $application->status,
            externalReference: $application->externalReference,
            failureReason: $application->failureReason,
            version: $application->version,
            submittedAt: $application->submittedAt,
            updatedAt: $application->updatedAt,
            processingAt: $application->processingAt,
            completedAt: $application->completedAt,
        );
    }
}
