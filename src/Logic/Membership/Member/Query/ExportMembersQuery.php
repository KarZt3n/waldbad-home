<?php

namespace App\Logic\Membership\Member\Query;

use App\Logic\Membership\Member\Dto\MemberSnapshotResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;

/**
 * Alle Mitglieder für den Export — anders als die übrigen Abfragen einschließlich gelöschter
 * Mitglieder und mit Zeitstempeln, damit ein Import den Stand 1:1 wiederherstellen kann.
 */
readonly class ExportMembersQuery
{
    public function __construct(private MemberManagerInterface $manager)
    {
    }

    /** @return list<MemberSnapshotResponse> */
    public function execute(): array
    {
        return array_map(MemberSnapshotResponse::fromSnapshot(...), $this->manager->findAllSnapshots());
    }
}
