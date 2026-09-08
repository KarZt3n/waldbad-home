<?php

namespace App\Logic\Membership\Member\Manager;

use App\Logic\Membership\Member\Exception\MemberNotFoundException;
use App\Logic\Membership\Member\MemberProcessorInterface;
use App\Logic\Membership\Member\MemberProviderInterface;
use App\Logic\Membership\Member\Model\Member;

readonly class MemberManager implements MemberManagerInterface
{
    public function __construct(
        private MemberProviderInterface $provider,
        private MemberProcessorInterface $processor,
    ) {
    }

    public function get(string $id): Member
    {
        return $this->provider->find($id) ?? throw new MemberNotFoundException($id);
    }

    public function findByMemberNumber(string $memberNumber): ?Member
    {
        return $this->provider->findByMemberNumber($memberNumber);
    }

    public function findByPrimaryMemberNumber(string $primaryMemberNumber): array
    {
        return $this->provider->findByPrimaryMemberNumber($primaryMemberNumber);
    }

    public function findByPayerMemberId(string $payerMemberId): array
    {
        return $this->provider->findByPayerMemberId($payerMemberId);
    }

    public function search(?string $term): array
    {
        return $this->provider->search($term);
    }

    public function save(Member $member): Member
    {
        return $this->processor->save($member);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }
}
