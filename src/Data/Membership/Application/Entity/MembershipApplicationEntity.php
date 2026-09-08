<?php

namespace App\Data\Membership\Application\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'membership_application')]
#[ORM\Index(name: 'idx_membership_submitted_at', columns: ['submitted_at'])]
class MembershipApplicationEntity
{
    /**
     * @var Collection<int, MembershipApplicantEntity>
     */
    #[ORM\OneToMany(targetEntity: MembershipApplicantEntity::class, mappedBy: 'application', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $applicants;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $membershipType,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $accountHolder,
        #[ORM\Column(type: Types::TEXT)]
        private string $iban,
        #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
        private ?string $bankName,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $signerName,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $emailConsent,
        #[ORM\Column(type: Types::STRING, length: 40)]
        private string $declarationVersion,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $submittedAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $releasedAt = null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $releasedMemberIds = null,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $rejectedAt = null,
        #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
        private ?string $rejectionReason = null,
    ) {
        $this->applicants = new ArrayCollection();
    }

    public function addApplicant(MembershipApplicantEntity $applicant): void
    {
        $this->applicants->add($applicant);
    }

    /**
     * @return list<MembershipApplicantEntity>
     */
    public function getApplicants(): array
    {
        return array_values($this->applicants->toArray());
    }

    public function getId(): string { return $this->id; }
    public function getMembershipType(): string { return $this->membershipType; }
    public function getAccountHolder(): string { return $this->accountHolder; }
    public function getIban(): string { return $this->iban; }
    public function getBankName(): ?string { return $this->bankName; }
    public function getSignerName(): string { return $this->signerName; }
    public function hasEmailConsent(): bool { return $this->emailConsent; }
    public function getDeclarationVersion(): string { return $this->declarationVersion; }
    public function getVersion(): int { return $this->version; }
    public function getSubmittedAt(): \DateTimeImmutable { return $this->submittedAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getReleasedAt(): ?\DateTimeImmutable { return $this->releasedAt; }
    public function getReleasedMemberIds(): ?string { return $this->releasedMemberIds; }
    public function getRejectedAt(): ?\DateTimeImmutable { return $this->rejectedAt; }
    public function getRejectionReason(): ?string { return $this->rejectionReason; }

    public function release(?\DateTimeImmutable $releasedAt, ?string $releasedMemberIds, \DateTimeImmutable $updatedAt): void
    {
        $this->releasedAt = $releasedAt;
        $this->releasedMemberIds = $releasedMemberIds;
        $this->updatedAt = $updatedAt;
    }

    public function reject(?\DateTimeImmutable $rejectedAt, ?string $rejectionReason, \DateTimeImmutable $updatedAt): void
    {
        $this->rejectedAt = $rejectedAt;
        $this->rejectionReason = $rejectionReason;
        $this->updatedAt = $updatedAt;
    }
}
