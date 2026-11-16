<?php

namespace App\Logic\Membership\Member;

use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberSnapshot;

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

    /**
     * Markiert alle Mitglieder in einem gemeinsamen Schreibvorgang als gelöscht — entweder alle oder keines.
     *
     * @param list<string> $ids
     */
    public function deleteAll(array $ids): void;

    /**
     * Schreibt die Mitglieder exakt so, wie übergeben (IDs, Bemerkungen, Gebühren, Zeitstempel,
     * Soft-Delete) — in einem gemeinsamen Schreibvorgang, entweder alle oder keines.
     *
     * @param list<MemberSnapshot> $snapshots
     */
    public function restoreAll(array $snapshots): void;
}
