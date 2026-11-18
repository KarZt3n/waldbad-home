<?php

namespace App\Logic\Membership\Cancellation\Manager;

use App\Logic\Membership\Cancellation\Exception\MemberCancellationNotFoundException;
use App\Logic\Membership\Cancellation\MemberCancellationProcessorInterface;
use App\Logic\Membership\Cancellation\MemberCancellationProviderInterface;
use App\Logic\Membership\Cancellation\Model\MemberCancellation;

readonly class MemberCancellationManager implements MemberCancellationManagerInterface
{
    public function __construct(
        private MemberCancellationProviderInterface $provider,
        private MemberCancellationProcessorInterface $processor,
    ) {
    }

    public function get(string $id): MemberCancellation
    {
        return $this->provider->find($id) ?? throw new MemberCancellationNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function openByMemberId(): array
    {
        $open = [];
        foreach ($this->provider->findOpen() as $cancellation) {
            $open[$cancellation->memberId] = $cancellation;
        }

        return $open;
    }

    public function save(MemberCancellation $cancellation): MemberCancellation
    {
        return $this->processor->save($cancellation);
    }
}
