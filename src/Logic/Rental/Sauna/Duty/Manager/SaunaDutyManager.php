<?php

namespace App\Logic\Rental\Sauna\Duty\Manager;

use App\Logic\Rental\Sauna\Duty\Exception\SaunaDutyNotFoundException;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;
use App\Logic\Rental\Sauna\Duty\SaunaDutyProcessorInterface;
use App\Logic\Rental\Sauna\Duty\SaunaDutyProviderInterface;

readonly class SaunaDutyManager implements SaunaDutyManagerInterface
{
    public function __construct(
        private SaunaDutyProviderInterface $provider,
        private SaunaDutyProcessorInterface $processor,
    ) {
    }

    public function get(string $id): SaunaDuty
    {
        return $this->provider->find($id) ?? throw new SaunaDutyNotFoundException($id);
    }

    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->provider->findBetween($from, $to);
    }

    public function save(SaunaDuty $duty): SaunaDuty
    {
        return $this->processor->save($duty);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }
}
