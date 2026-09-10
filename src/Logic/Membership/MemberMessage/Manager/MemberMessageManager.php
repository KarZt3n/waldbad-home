<?php

namespace App\Logic\Membership\MemberMessage\Manager;

use App\Logic\Membership\MemberMessage\Exception\MemberMessageNotFoundException;
use App\Logic\Membership\MemberMessage\MemberMessageProcessorInterface;
use App\Logic\Membership\MemberMessage\MemberMessageProviderInterface;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;

readonly class MemberMessageManager implements MemberMessageManagerInterface
{
    public function __construct(
        private MemberMessageProviderInterface $provider,
        private MemberMessageProcessorInterface $processor,
    ) {
    }

    public function get(string $id): MemberMessage
    {
        return $this->provider->find($id) ?? throw new MemberMessageNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function save(MemberMessage $message): MemberMessage
    {
        return $this->processor->save($message);
    }
}
