<?php

namespace App\Data\Membership\MemberAccess\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_access_token')]
#[ORM\UniqueConstraint(name: 'uniq_member_access_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_member_access_token_expires', columns: ['expires_at'])]
class MemberAccessTokenEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $email,
        #[ORM\Column(name: 'token_hash', type: Types::STRING, length: 64)]
        private string $tokenHash,
        #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'primary_member_number', type: Types::STRING, length: 20)]
        private string $primaryMemberNumber,
        #[ORM\Column(name: 'password_hash', type: Types::STRING, length: 255)]
        private string $passwordHash,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
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

    public function getPrimaryMemberNumber(): string
    {
        return $this->primaryMemberNumber;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }
}
