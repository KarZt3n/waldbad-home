<?php

namespace App\Logic\Membership\Application\Dto;

use App\Logic\Membership\Application\Model\Applicant;
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
        public int $version,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $releasedAt,
        /** @var list<string>|null */
        public ?array $releasedMemberIds,
        public ?\DateTimeImmutable $rejectedAt,
        public ?string $rejectionReason,
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
            version: $application->version,
            submittedAt: $application->submittedAt,
            updatedAt: $application->updatedAt,
            releasedAt: $application->releasedAt,
            releasedMemberIds: $application->releasedMemberIds,
            rejectedAt: $application->rejectedAt,
            rejectionReason: $application->rejectionReason,
        );
    }
}
