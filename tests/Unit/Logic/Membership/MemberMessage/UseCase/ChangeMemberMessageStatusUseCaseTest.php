<?php

namespace App\Tests\Unit\Logic\Membership\MemberMessage\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\MemberMessage\Manager\MemberMessageManagerInterface;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;
use App\Logic\Membership\MemberMessage\UseCase\ChangeMemberMessageStatusUseCase;
use PHPUnit\Framework\TestCase;

final class ChangeMemberMessageStatusUseCaseTest extends TestCase
{
    public function testUpdatesTheStatusAndTimestamp(): void
    {
        $submittedAt = new \DateTimeImmutable('2026-06-01T10:00:00+02:00');
        $existing = new MemberMessage('id-1', 'member-1', 'Bad-001', 'Erika Musterfrau', 'Text', MemberMessageStatus::New, $submittedAt, $submittedAt);

        $manager = $this->createMock(MemberMessageManagerInterface::class);
        $manager->method('get')->willReturn($existing);
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (MemberMessage $message): MemberMessage {
                self::assertSame(MemberMessageStatus::Resolved, $message->status);

                return $message;
            },
        );

        $now = new \DateTimeImmutable('2026-06-02T09:00:00+02:00');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $response = (new ChangeMemberMessageStatusUseCase($manager, $clock))->execute('id-1', MemberMessageStatus::Resolved);

        self::assertSame(MemberMessageStatus::Resolved, $response->status);
        self::assertEquals($now, $response->updatedAt);
    }
}
