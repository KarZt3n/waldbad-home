<?php

namespace App\Tests\Unit\Logic\Membership\MemberMessage\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;
use PHPUnit\Framework\TestCase;

final class MemberMessageTest extends TestCase
{
    public function testRejectsAnEmptyMessage(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new MemberMessage('id-1', 'member-1', 'Bad-001', 'Erika Musterfrau', '   ', MemberMessageStatus::New, new \DateTimeImmutable(), new \DateTimeImmutable());
    }

    public function testChangeStatusKeepsEverythingElseButUpdatesTheTimestamp(): void
    {
        $submittedAt = new \DateTimeImmutable('2026-06-01T10:00:00+02:00');
        $message = new MemberMessage('id-1', 'member-1', 'Bad-001', 'Erika Musterfrau', 'Meine Adresse hat sich geändert.', MemberMessageStatus::New, $submittedAt, $submittedAt);

        $updatedAt = new \DateTimeImmutable('2026-06-02T09:00:00+02:00');
        $resolved = $message->changeStatus(MemberMessageStatus::Resolved, $updatedAt);

        self::assertSame(MemberMessageStatus::Resolved, $resolved->status);
        self::assertSame($updatedAt, $resolved->updatedAt);
        self::assertSame($submittedAt, $resolved->submittedAt);
        self::assertSame($message->message, $resolved->message);
    }
}
