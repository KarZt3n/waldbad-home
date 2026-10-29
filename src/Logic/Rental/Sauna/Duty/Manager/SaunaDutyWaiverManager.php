<?php

namespace App\Logic\Rental\Sauna\Duty\Manager;

use App\Logic\Rental\Sauna\Duty\Exception\SaunaDutyWaiverNotFoundException;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;
use App\Logic\Rental\Sauna\Duty\SaunaDutyWaiverProcessorInterface;
use App\Logic\Rental\Sauna\Duty\SaunaDutyWaiverProviderInterface;

readonly class SaunaDutyWaiverManager implements SaunaDutyWaiverManagerInterface
{
    public function __construct(
        private SaunaDutyWaiverProviderInterface $provider,
        private SaunaDutyWaiverProcessorInterface $processor,
    ) {
    }

    public function get(string $id): SaunaDutyWaiver
    {
        return $this->provider->find($id) ?? throw new SaunaDutyWaiverNotFoundException($id);
    }

    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->provider->findBetween($from, $to);
    }

    public function save(SaunaDutyWaiver $waiver): SaunaDutyWaiver
    {
        return $this->processor->save($waiver);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }
}
