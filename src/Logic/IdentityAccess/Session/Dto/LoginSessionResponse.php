<?php

namespace App\Logic\IdentityAccess\Session\Dto;

use App\Logic\IdentityAccess\Session\Model\LoginSessionEndReason;

readonly class LoginSessionResponse
{
    public function __construct(
        public string $id,
        public string $userId,
        public ?string $userDisplayName,
        public ?string $userEmail,
        public \DateTimeImmutable $startedAt,
        public \DateTimeImmutable $lastActivityAt,
        public ?\DateTimeImmutable $endedAt,
        public ?LoginSessionEndReason $endReason,
        public ?string $userAgent,
    ) {
    }
}
