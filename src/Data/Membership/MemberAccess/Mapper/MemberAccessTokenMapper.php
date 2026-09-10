<?php

namespace App\Data\Membership\MemberAccess\Mapper;

use App\Data\Membership\MemberAccess\Entity\MemberAccessTokenEntity;
use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;

readonly class MemberAccessTokenMapper
{
    public function toModel(MemberAccessTokenEntity $entity): MemberAccessToken
    {
        return new MemberAccessToken(
            id: $entity->getId(),
            email: $entity->getEmail(),
            tokenHash: $entity->getTokenHash(),
            expiresAt: $entity->getExpiresAt(),
            primaryMemberNumber: $entity->getPrimaryMemberNumber(),
            passwordHash: $entity->getPasswordHash(),
        );
    }

    public function createEntity(MemberAccessToken $token): MemberAccessTokenEntity
    {
        return new MemberAccessTokenEntity(
            id: $token->id,
            email: $token->email,
            tokenHash: $token->tokenHash,
            expiresAt: $token->expiresAt,
            primaryMemberNumber: $token->primaryMemberNumber,
            passwordHash: $token->passwordHash,
        );
    }
}
