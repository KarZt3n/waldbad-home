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
    public function findByEmail(string $email): array;

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

    /**
     * Speichert alle Mitglieder in einem gemeinsamen Schreibvorgang — entweder alle oder keines.
     * Für Sammelläufe über viele Mitglieder gedacht, bei denen einzelne `save()`-Aufrufe zu
     * langsam wären.
     *
     * @param list<Member> $members
     */
    public function saveAll(array $members): void;

    public function delete(string $id): void;
}
