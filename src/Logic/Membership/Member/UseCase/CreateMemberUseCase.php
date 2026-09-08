<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Orchestrator\MemberOnboardingOrchestrator;

readonly class CreateMemberUseCase
{
    public function __construct(private MemberOnboardingOrchestrator $orchestrator)
    {
    }

    public function execute(CreateMemberRequest $request): MemberResponse
    {
        return MemberResponse::fromMember($this->orchestrator->createFromRequest($request));
    }
}
