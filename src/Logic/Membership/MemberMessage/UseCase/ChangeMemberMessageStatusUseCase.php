<?php

namespace App\Logic\Membership\MemberMessage\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\MemberMessage\Dto\MemberMessageResponse;
use App\Logic\Membership\MemberMessage\Manager\MemberMessageManagerInterface;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;

readonly class ChangeMemberMessageStatusUseCase
{
    public function __construct(
        private MemberMessageManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id, MemberMessageStatus $status): MemberMessageResponse
    {
        $message = $this->manager->get($id)->changeStatus($status, $this->clock->now());

        return MemberMessageResponse::fromMessage($this->manager->save($message));
    }
}
