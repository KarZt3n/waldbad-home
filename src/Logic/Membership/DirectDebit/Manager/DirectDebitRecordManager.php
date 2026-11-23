<?php

namespace App\Logic\Membership\DirectDebit\Manager;

use App\Logic\Membership\DirectDebit\DirectDebitRecordProcessorInterface;
use App\Logic\Membership\DirectDebit\DirectDebitRecordProviderInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;

readonly class DirectDebitRecordManager implements DirectDebitRecordManagerInterface
{
    public function __construct(
        private DirectDebitRecordProviderInterface $provider,
        private DirectDebitRecordProcessorInterface $processor,
    ) {
    }

    public function findByPayerMemberId(string $payerMemberId): array
    {
        return $this->provider->findByPayerMemberId($payerMemberId);
    }

    public function findAll(): array
    {
        return $this->provider->findAll();
    }

    public function findCollectedChargeIds(): array
    {
        return $this->provider->findCollectedChargeIds();
    }

    public function saveAll(array $records, array $collectedChargeIdsByRecordId = []): void
    {
        $this->processor->saveAll($records, $collectedChargeIdsByRecordId);
    }

    public function reassignPayer(string $fromPayerMemberId, string $toPayerMemberId): void
    {
        $this->processor->reassignPayer($fromPayerMemberId, $toPayerMemberId);
    }
}
