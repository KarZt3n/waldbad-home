<?php

namespace App\Logic\Membership\ContributionRate\Manager;

use App\Logic\Membership\ContributionRate\ContributionRateProcessorInterface;
use App\Logic\Membership\ContributionRate\ContributionRateProviderInterface;
use App\Logic\Membership\ContributionRate\Exception\ContributionRateNotFoundException;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;

readonly class ContributionRateManager implements ContributionRateManagerInterface
{
    public function __construct(
        private ContributionRateProviderInterface $provider,
        private ContributionRateProcessorInterface $processor,
    ) {
    }

    public function get(string $id): ContributionRate
    {
        return $this->provider->find($id) ?? throw new ContributionRateNotFoundException($id);
    }

    public function findByCategory(ContributionCategory $category): ?ContributionRate
    {
        return $this->provider->findByCategory($category);
    }

    public function list(): array
    {
        return $this->provider->findAll();
    }

    public function save(ContributionRate $rate): ContributionRate
    {
        return $this->processor->save($rate);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }
}
