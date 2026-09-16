<?php

namespace App\Data\IdentityAccess\Session\Mapper;

use App\Data\IdentityAccess\Session\Entity\RefreshTokenEntity;
use App\Logic\IdentityAccess\Session\Model\RefreshToken;

readonly class RefreshTokenMapper
{
    public function toModel(RefreshTokenEntity $entity): RefreshToken
    {
        return new RefreshToken(
            id: $entity->getId(),
            userId: $entity->getUserId(),
            tokenHash: $entity->getTokenHash(),
            expiresAt: $entity->getExpiresAt(),
            absoluteExpiresAt: $entity->getAbsoluteExpiresAt(),
            createdAt: $entity->getCreatedAt(),
            revokedAt: $entity->getRevokedAt(),
        );
    }

    public function createEntity(RefreshToken $token): RefreshTokenEntity
    {
        return new RefreshTokenEntity(
            id: $token->id,
            userId: $token->userId,
            tokenHash: $token->tokenHash,
            expiresAt: $token->expiresAt,
            absoluteExpiresAt: $token->absoluteExpiresAt,
            createdAt: $token->createdAt,
            revokedAt: $token->revokedAt,
        );
    }
}
