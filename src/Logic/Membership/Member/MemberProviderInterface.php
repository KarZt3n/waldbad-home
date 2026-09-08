<?php

namespace App\Logic\Membership\Member;

use App\Logic\Membership\Member\Model\Member;

interface MemberProviderInterface
{
    public function find(string $id): ?Member;

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
}
