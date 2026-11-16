<?php

namespace App\Logic\Membership\Member\Model;

/**
 * Vollständiger gespeicherter Stand eines Mitglieds inklusive technischer Zeitstempel und
 * Soft-Delete-Markierung — Grundlage für einen Export, der sich per Import 1:1 wiederherstellen
 * lässt (siehe `ExportMembersQuery`, `RestoreMembersUseCase`).
 */
readonly class MemberSnapshot
{
    public function __construct(
        public Member $member,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $deletedAt,
    ) {
    }
}
