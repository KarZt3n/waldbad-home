<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Dto\UpdateMemberRequest;
use App\Logic\Membership\Member\Mapping\MemberModelFactory;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;

readonly class UpdateMemberUseCase
{
    public function __construct(
        private MemberManagerInterface $manager,
        private MemberModelFactory $factory,
    ) {
    }

    public function execute(UpdateMemberRequest $request): MemberResponse
    {
        $current = $this->manager->get($request->id);
        $member = $this->factory->rebuildFromRequest($request, $current);

        return MemberResponse::fromMember($this->manager->save($member));
    }
}
