<?php

namespace App\Data\Rental\Sauna\Booking\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sauna_booking_approval_token')]
#[ORM\UniqueConstraint(name: 'uniq_sauna_booking_approval_token_hash', columns: ['token_hash'])]
class SaunaBookingApprovalTokenEntity
{
    /**
     * @param list<string> $bookingIds
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(name: 'token_hash', type: Types::STRING, length: 64)]
        private string $tokenHash,
        #[ORM\Column(name: 'booking_ids', type: Types::JSON)]
        private array $bookingIds,
        #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'used_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $usedAt = null,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getTokenHash(): string { return $this->tokenHash; }
    /** @return list<string> */
    public function getBookingIds(): array { return $this->bookingIds; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getUsedAt(): ?\DateTimeImmutable { return $this->usedAt; }

    public function markUsed(\DateTimeImmutable $usedAt): void
    {
        $this->usedAt = $usedAt;
    }
}
