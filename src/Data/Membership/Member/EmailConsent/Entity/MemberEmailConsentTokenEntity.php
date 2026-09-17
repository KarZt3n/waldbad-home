<?php

namespace App\Data\Membership\Member\EmailConsent\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_email_consent_token')]
#[ORM\UniqueConstraint(name: 'uniq_member_email_consent_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_member_email_consent_token_expires', columns: ['expires_at'])]
class MemberEmailConsentTokenEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(name: 'member_id', type: Types::STRING, length: 36)]
        private string $memberId,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $email,
        #[ORM\Column(name: 'token_hash', type: Types::STRING, length: 64)]
        private string $tokenHash,
        #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'confirmed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $confirmedAt = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMemberId(): string
    {
        return $this->memberId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function markConfirmed(\DateTimeImmutable $at): void
    {
        $this->confirmedAt = $at;
    }
}
