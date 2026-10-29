<?php

namespace App\Logic\Rental\Sauna\Booking\Manager;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingApprovalToken;
use App\Logic\Rental\Sauna\Booking\SaunaBookingApprovalTokenProcessorInterface;
use App\Logic\Rental\Sauna\Booking\SaunaBookingApprovalTokenProviderInterface;

readonly class SaunaBookingApprovalTokenManager implements SaunaBookingApprovalTokenManagerInterface
{
    public function __construct(
        private SaunaBookingApprovalTokenProviderInterface $provider,
        private SaunaBookingApprovalTokenProcessorInterface $processor,
    ) {
    }

    public function findByHash(string $tokenHash): ?SaunaBookingApprovalToken
    {
        return $this->provider->findByHash($tokenHash);
    }

    public function save(SaunaBookingApprovalToken $token): void
    {
        $this->processor->save($token);
    }
}
