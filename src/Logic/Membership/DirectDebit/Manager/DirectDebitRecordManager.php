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

    public function save(DirectDebitRecord $record): DirectDebitRecord
    {
        return $this->processor->save($record);
    }
}
