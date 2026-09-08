<?php

namespace App\Data\Membership\Member\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member')]
#[ORM\UniqueConstraint(name: 'uniq_member_number', columns: ['member_number'])]
#[ORM\Index(name: 'idx_member_primary_member_number', columns: ['primary_member_number'])]
class MemberEntity
{
    /**
     * @var Collection<int, MemberRemarkEntity>
     */
    #[ORM\OneToMany(targetEntity: MemberRemarkEntity::class, mappedBy: 'member', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $remarks;

    /**
     * @var Collection<int, MemberContributionChargeEntity>
     */
    #[ORM\OneToMany(targetEntity: MemberContributionChargeEntity::class, mappedBy: 'member', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['chargedAt' => 'ASC'])]
    private Collection $oneTimeCharges;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $memberNumber,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $primaryMemberNumber,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $salutation,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $lastName,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $firstName,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $birthDate,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $street,
        #[ORM\Column(type: Types::STRING, length: 5)]
        private string $postalCode,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $city,
        #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
        private ?string $email,
        #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
        private ?string $phone,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $familyRole,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $joinedAt,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $leftAt,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $active,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $function,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $contributionLiable,
        #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
        private ?string $accountHolder,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $iban,
        #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
        private ?string $bankName,
        #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
        private ?string $mandateReference,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $mandateValidFrom,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $mandateValidUntil,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $paymentMethod,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $paymentInterval,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $paymentDay,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $payerType,
        #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
        private ?string $payerMemberId,
        #[ORM\Column(type: Types::INTEGER)]
        private int $nextBookingMonth,
        #[ORM\Column(type: Types::INTEGER)]
        private int $nextBookingYear,
        #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
        private ?string $contributionCategory,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private ?int $contributionAmountCents,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private ?int $workAssignmentSurchargeCents,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->remarks = new ArrayCollection();
        $this->oneTimeCharges = new ArrayCollection();
    }

    public function addRemark(MemberRemarkEntity $remark): void
    {
        $this->remarks->add($remark);
    }

    /**
     * @return list<MemberRemarkEntity>
     */
    public function getRemarks(): array
    {
        return array_values($this->remarks->toArray());
    }

    public function addOneTimeCharge(MemberContributionChargeEntity $charge): void
    {
        $this->oneTimeCharges->add($charge);
    }

    /**
     * @return list<MemberContributionChargeEntity>
     */
    public function getOneTimeCharges(): array
    {
        return array_values($this->oneTimeCharges->toArray());
    }

    public function getId(): string { return $this->id; }
    public function getMemberNumber(): string { return $this->memberNumber; }
    public function getPrimaryMemberNumber(): string { return $this->primaryMemberNumber; }
    public function getSalutation(): string { return $this->salutation; }
    public function getLastName(): string { return $this->lastName; }
    public function getFirstName(): string { return $this->firstName; }
    public function getBirthDate(): \DateTimeImmutable { return $this->birthDate; }
    public function getStreet(): string { return $this->street; }
    public function getPostalCode(): string { return $this->postalCode; }
    public function getCity(): string { return $this->city; }
    public function getEmail(): ?string { return $this->email; }
    public function getPhone(): ?string { return $this->phone; }
    public function getFamilyRole(): string { return $this->familyRole; }
    public function getJoinedAt(): \DateTimeImmutable { return $this->joinedAt; }
    public function getLeftAt(): ?\DateTimeImmutable { return $this->leftAt; }
    public function isActive(): bool { return $this->active; }
    public function getFunction(): string { return $this->function; }
    public function isContributionLiable(): bool { return $this->contributionLiable; }
    public function getAccountHolder(): ?string { return $this->accountHolder; }
    public function getIban(): ?string { return $this->iban; }
    public function getBankName(): ?string { return $this->bankName; }
    public function getMandateReference(): ?string { return $this->mandateReference; }
    public function getMandateValidFrom(): ?\DateTimeImmutable { return $this->mandateValidFrom; }
    public function getMandateValidUntil(): ?\DateTimeImmutable { return $this->mandateValidUntil; }
    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function getPaymentInterval(): string { return $this->paymentInterval; }
    public function getPaymentDay(): string { return $this->paymentDay; }
    public function getPayerType(): string { return $this->payerType; }
    public function getPayerMemberId(): ?string { return $this->payerMemberId; }
    public function getNextBookingMonth(): int { return $this->nextBookingMonth; }
    public function getNextBookingYear(): int { return $this->nextBookingYear; }
    public function getContributionCategory(): ?string { return $this->contributionCategory; }
    public function getContributionAmountCents(): ?int { return $this->contributionAmountCents; }
    public function getWorkAssignmentSurchargeCents(): ?int { return $this->workAssignmentSurchargeCents; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getVersion(): int { return $this->version; }

    public function update(
        string $memberNumber,
        string $primaryMemberNumber,
        string $salutation,
        string $lastName,
        string $firstName,
        \DateTimeImmutable $birthDate,
        string $street,
        string $postalCode,
        string $city,
        ?string $email,
        ?string $phone,
        string $familyRole,
        \DateTimeImmutable $joinedAt,
        ?\DateTimeImmutable $leftAt,
        bool $active,
        string $function,
        bool $contributionLiable,
        ?string $accountHolder,
        ?string $iban,
        ?string $bankName,
        ?string $mandateReference,
        ?\DateTimeImmutable $mandateValidFrom,
        ?\DateTimeImmutable $mandateValidUntil,
        string $paymentMethod,
        string $paymentInterval,
        string $paymentDay,
        string $payerType,
        ?string $payerMemberId,
        int $nextBookingMonth,
        int $nextBookingYear,
        ?string $contributionCategory,
        ?int $contributionAmountCents,
        ?int $workAssignmentSurchargeCents,
        \DateTimeImmutable $updatedAt,
    ): void {
        $this->memberNumber = $memberNumber;
        $this->primaryMemberNumber = $primaryMemberNumber;
        $this->salutation = $salutation;
        $this->lastName = $lastName;
        $this->firstName = $firstName;
        $this->birthDate = $birthDate;
        $this->street = $street;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->email = $email;
        $this->phone = $phone;
        $this->familyRole = $familyRole;
        $this->joinedAt = $joinedAt;
        $this->leftAt = $leftAt;
        $this->active = $active;
        $this->function = $function;
        $this->contributionLiable = $contributionLiable;
        $this->accountHolder = $accountHolder;
        $this->iban = $iban;
        $this->bankName = $bankName;
        $this->mandateReference = $mandateReference;
        $this->mandateValidFrom = $mandateValidFrom;
        $this->mandateValidUntil = $mandateValidUntil;
        $this->paymentMethod = $paymentMethod;
        $this->paymentInterval = $paymentInterval;
        $this->paymentDay = $paymentDay;
        $this->payerType = $payerType;
        $this->payerMemberId = $payerMemberId;
        $this->nextBookingMonth = $nextBookingMonth;
        $this->nextBookingYear = $nextBookingYear;
        $this->contributionCategory = $contributionCategory;
        $this->contributionAmountCents = $contributionAmountCents;
        $this->workAssignmentSurchargeCents = $workAssignmentSurchargeCents;
        $this->updatedAt = $updatedAt;
    }
}
