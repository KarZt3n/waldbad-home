<?php

namespace App\Data\IdentityAccess\Session\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_refresh_token')]
#[ORM\UniqueConstraint(name: 'uniq_admin_refresh_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_admin_refresh_token_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_admin_refresh_token_expires', columns: ['expires_at'])]
class RefreshTokenEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(name: 'user_id', type: Types::STRING, length: 36)]
        private string $userId,
        #[ORM\Column(name: 'token_hash', type: Types::STRING, length: 64)]
        private string $tokenHash,
        #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'absolute_expires_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $absoluteExpiresAt,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'revoked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $revokedAt = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getAbsoluteExpiresAt(): \DateTimeImmutable
    {
        return $this->absoluteExpiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(\DateTimeImmutable $at): void
    {
        $this->revokedAt = $at;
    }
}
