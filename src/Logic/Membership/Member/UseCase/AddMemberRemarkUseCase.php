<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\Member\Dto\AddRemarkRequest;
use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Remark;

readonly class AddMemberRemarkUseCase
{
    public function __construct(
        private MemberManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(AddRemarkRequest $request): MemberResponse
    {
        $member = $this->manager->get($request->memberId);
        $remark = new Remark(
            id: $this->identifierGenerator->generate(),
            text: trim($request->text),
            authorDisplayName: $request->authorDisplayName,
            createdAt: $this->clock->now(),
        );

        return MemberResponse::fromMember($this->manager->save($member->withRemark($remark)));
    }
}
