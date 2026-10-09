<?php

namespace App\Data\IdentityAccess\Session\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_login_session')]
#[ORM\Index(name: 'idx_admin_login_session_user_ended', columns: ['user_id', 'ended_at'])]
#[ORM\Index(name: 'idx_admin_login_session_started', columns: ['started_at'])]
class LoginSessionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(name: 'user_id', type: Types::STRING, length: 36)]
        private string $userId,
        #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $startedAt,
        #[ORM\Column(name: 'last_activity_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $lastActivityAt,
        #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'user_agent', type: Types::STRING, length: 255, nullable: true)]
        private ?string $userAgent,
        #[ORM\Column(name: 'ended_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $endedAt,
        #[ORM\Column(name: 'end_reason', type: Types::STRING, length: 20, nullable: true)]
        private ?string $endReason,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getUserId(): string { return $this->userId; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getLastActivityAt(): \DateTimeImmutable { return $this->lastActivityAt; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function getEndedAt(): ?\DateTimeImmutable { return $this->endedAt; }
    public function getEndReason(): ?string { return $this->endReason; }

    public function update(\DateTimeImmutable $lastActivityAt, \DateTimeImmutable $expiresAt, ?\DateTimeImmutable $endedAt, ?string $endReason): void
    {
        $this->lastActivityAt = $lastActivityAt;
        $this->expiresAt = $expiresAt;
        $this->endedAt = $endedAt;
        $this->endReason = $endReason;
    }
}
