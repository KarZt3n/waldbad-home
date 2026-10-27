<?php

namespace App\Logic\Membership\Member;

use App\Logic\Membership\Member\Model\Member;

interface MemberProcessorInterface
{
    public function save(Member $member): Member;

    /**
     * Speichert alle Mitglieder in einem gemeinsamen Schreibvorgang — entweder alle oder keines.
     *
     * @param list<Member> $members
     */
    public function saveAll(array $members): void;

    public function delete(string $id): void;
}
