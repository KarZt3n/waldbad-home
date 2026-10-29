<?php

namespace App\Data\Rental\Sauna\Booking\Mapper;

use App\Data\Rental\Sauna\Booking\Entity\SaunaBookingApprovalTokenEntity;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingApprovalToken;

readonly class SaunaBookingApprovalTokenMapper
{
    public function toModel(SaunaBookingApprovalTokenEntity $entity): ?SaunaBookingApprovalToken
    {
        $bookingIds = array_values(array_filter($entity->getBookingIds(), is_string(...)));
        if ($bookingIds === []) {
            return null;
        }

        return new SaunaBookingApprovalToken(
            id: $entity->getId(),
            tokenHash: $entity->getTokenHash(),
            bookingIds: $bookingIds,
            expiresAt: $entity->getExpiresAt(),
            usedAt: $entity->getUsedAt(),
        );
    }

    public function createEntity(SaunaBookingApprovalToken $token): SaunaBookingApprovalTokenEntity
    {
        return new SaunaBookingApprovalTokenEntity(
            id: $token->id,
            tokenHash: $token->tokenHash,
            bookingIds: $token->bookingIds,
            expiresAt: $token->expiresAt,
            usedAt: $token->usedAt,
        );
    }
}
