<?php

namespace App\Tests\Unit\Logic\IdentityAccess\Session\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\IdentityAccess\Session\Model\LoginSession;
use App\Logic\IdentityAccess\Session\Model\LoginSessionEndReason;
use PHPUnit\Framework\TestCase;

final class LoginSessionTest extends TestCase
{
    public function testOpenSessionIsActiveUntilItsRefreshTokenExpires(): void
    {
        $session = $this->session();

        self::assertNull($session->effectiveEndReason(new \DateTimeImmutable('2026-06-01T10:59:59')));
        self::assertNull($session->effectiveEndedAt(new \DateTimeImmutable('2026-06-01T10:59:59')));
        self::assertSame(LoginSessionEndReason::Expired, $session->effectiveEndReason(new \DateTimeImmutable('2026-06-01T11:00:00')));
        self::assertEquals(new \DateTimeImmutable('2026-06-01T11:00:00'), $session->effectiveEndedAt(new \DateTimeImmutable('2026-06-01T12:00:00')));
    }

    public function testExtendMovesLastActivityAndExpiry(): void
    {
        $extended = $this->session()->extend(new \DateTimeImmutable('2026-06-01T10:10:00'), new \DateTimeImmutable('2026-06-01T11:10:00'));

        self::assertEquals(new \DateTimeImmutable('2026-06-01T10:10:00'), $extended->lastActivityAt);
        self::assertNull($extended->effectiveEndReason(new \DateTimeImmutable('2026-06-01T11:05:00')));
    }

    public function testEndedSessionKeepsItsFirstEndReason(): void
    {
        $ended = $this->session()->end(new \DateTimeImmutable('2026-06-01T10:30:00'), LoginSessionEndReason::Inactivity);
        $endedAgain = $ended->end(new \DateTimeImmutable('2026-06-01T10:40:00'), LoginSessionEndReason::Logout);

        self::assertSame(LoginSessionEndReason::Inactivity, $endedAgain->effectiveEndReason(new \DateTimeImmutable('2026-06-01T12:00:00')));
        self::assertEquals(new \DateTimeImmutable('2026-06-01T10:30:00'), $endedAgain->endedAt);
        self::assertSame($ended, $ended->extend(new \DateTimeImmutable('2026-06-01T10:45:00'), new \DateTimeImmutable('2026-06-01T11:45:00')));
    }

    public function testEndRequiresTimestampAndReasonTogether(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new LoginSession(
            id: 'session-1',
            userId: 'user-1',
            startedAt: new \DateTimeImmutable('2026-06-01T10:00:00'),
            lastActivityAt: new \DateTimeImmutable('2026-06-01T10:00:00'),
            expiresAt: new \DateTimeImmutable('2026-06-01T11:00:00'),
            endedAt: new \DateTimeImmutable('2026-06-01T10:30:00'),
        );
    }

    private function session(): LoginSession
    {
        return new LoginSession(
            id: 'session-1',
            userId: 'user-1',
            startedAt: new \DateTimeImmutable('2026-06-01T10:00:00'),
            lastActivityAt: new \DateTimeImmutable('2026-06-01T10:00:00'),
            expiresAt: new \DateTimeImmutable('2026-06-01T11:00:00'),
            userAgent: 'Mozilla/5.0',
        );
    }
}
