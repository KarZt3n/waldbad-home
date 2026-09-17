<?php

namespace App\Data\Membership\Member\EmailConsent\Mapper;

use App\Data\Membership\Member\EmailConsent\Entity\MemberEmailConsentTokenEntity;
use App\Logic\Membership\Member\EmailConsent\Model\MemberEmailConsentToken;

readonly class MemberEmailConsentTokenMapper
{
    public function toModel(MemberEmailConsentTokenEntity $entity): MemberEmailConsentToken
    {
        return new MemberEmailConsentToken(
            id: $entity->getId(),
            memberId: $entity->getMemberId(),
            email: $entity->getEmail(),
            tokenHash: $entity->getTokenHash(),
            expiresAt: $entity->getExpiresAt(),
            confirmedAt: $entity->getConfirmedAt(),
        );
    }

    public function createEntity(MemberEmailConsentToken $token): MemberEmailConsentTokenEntity
    {
        return new MemberEmailConsentTokenEntity(
            id: $token->id,
            memberId: $token->memberId,
            email: $token->email,
            tokenHash: $token->tokenHash,
            expiresAt: $token->expiresAt,
            confirmedAt: $token->confirmedAt,
        );
    }
}
