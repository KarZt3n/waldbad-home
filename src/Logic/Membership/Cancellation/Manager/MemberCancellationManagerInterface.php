<?php

namespace App\Logic\Membership\Cancellation\Manager;

use App\Logic\Membership\Cancellation\Model\MemberCancellation;

interface MemberCancellationManagerInterface
{
    public function get(string $id): MemberCancellation;

    /**
     * @return list<MemberCancellation> neueste zuerst
     */
    public function all(): array;

    /**
     * Offene Kündigungen je Mitglied — je Mitglied gibt es höchstens eine.
     *
     * @return array<string, MemberCancellation>
     */
    public function openByMemberId(): array;

    public function save(MemberCancellation $cancellation): MemberCancellation;
}
