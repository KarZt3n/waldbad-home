<?php

namespace App\Logic\Membership\Member\Manager;

use App\Logic\Membership\Member\Model\Member;

interface MemberManagerInterface
{
    public function get(string $id): Member;

    public function findByMemberNumber(string $memberNumber): ?Member;

    /**
     * @return list<Member>
     */
    public function findByPrimaryMemberNumber(string $primaryMemberNumber): array;

    /**
     * @return list<Member>
     */
    public function findByPayerMemberId(string $payerMemberId): array;

    /**
     * @return list<Member>
     */
    public function search(?string $term): array;

    public function save(Member $member): Member;
}
