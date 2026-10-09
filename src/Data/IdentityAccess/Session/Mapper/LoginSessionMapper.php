<?php

namespace App\Data\IdentityAccess\Session\Mapper;

use App\Data\IdentityAccess\Session\Entity\LoginSessionEntity;
use App\Logic\IdentityAccess\Session\Model\LoginSession;
use App\Logic\IdentityAccess\Session\Model\LoginSessionEndReason;

readonly class LoginSessionMapper
{
    public function toModel(LoginSessionEntity $entity): LoginSession
    {
        $endReason = $entity->getEndReason();

        return new LoginSession(
            id: $entity->getId(),
            userId: $entity->getUserId(),
            startedAt: $entity->getStartedAt(),
            lastActivityAt: $entity->getLastActivityAt(),
            expiresAt: $entity->getExpiresAt(),
            userAgent: $entity->getUserAgent(),
            endedAt: $entity->getEndedAt(),
            endReason: $endReason === null ? null : LoginSessionEndReason::from($endReason),
        );
    }

    public function createEntity(LoginSession $session): LoginSessionEntity
    {
        return new LoginSessionEntity(
            id: $session->id,
            userId: $session->userId,
            startedAt: $session->startedAt,
            lastActivityAt: $session->lastActivityAt,
            expiresAt: $session->expiresAt,
            userAgent: $session->userAgent,
            endedAt: $session->endedAt,
            endReason: $session->endReason?->value,
        );
    }

    public function updateEntity(LoginSession $session, LoginSessionEntity $entity): void
    {
        $entity->update($session->lastActivityAt, $session->expiresAt, $session->endedAt, $session->endReason?->value);
    }
}
