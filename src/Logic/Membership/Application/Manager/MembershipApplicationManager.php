<?php

namespace App\Logic\Membership\Application\Manager;

use App\Logic\Membership\Application\Exception\MembershipApplicationNotFoundException;
use App\Logic\Membership\Application\MembershipApplicationProcessorInterface;
use App\Logic\Membership\Application\MembershipApplicationProviderInterface;
use App\Logic\Membership\Application\Model\MembershipApplication;

readonly class MembershipApplicationManager implements MembershipApplicationManagerInterface
{
    public function __construct(
        private MembershipApplicationProviderInterface $provider,
        private MembershipApplicationProcessorInterface $processor,
    ) {
    }

    public function get(string $id): MembershipApplication
    {
        return $this->provider->find($id) ?? throw new MembershipApplicationNotFoundException($id);
    }

    public function list(): array
    {
        return $this->provider->findAll();
    }

    public function save(MembershipApplication $application): MembershipApplication
    {
        return $this->processor->save($application);
    }
}
