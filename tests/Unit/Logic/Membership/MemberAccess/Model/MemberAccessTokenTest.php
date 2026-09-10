<?php

namespace App\Tests\Unit\Logic\Membership\MemberAccess\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;
use PHPUnit\Framework\TestCase;

final class MemberAccessTokenTest extends TestCase
{
    public function testRejectsAnInvalidEmail(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new MemberAccessToken('id-1', 'not-an-email', str_repeat('a', 64), new \DateTimeImmutable('+5 minutes'), 'Bad-001', 'hash-placeholder');
    }

    public function testRejectsAnEmptyTokenHash(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new MemberAccessToken('id-1', 'erika@example.test', '  ', new \DateTimeImmutable('+5 minutes'), 'Bad-001', 'hash-placeholder');
    }

    public function testRejectsAnEmptyPrimaryMemberNumber(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new MemberAccessToken('id-1', 'erika@example.test', str_repeat('a', 64), new \DateTimeImmutable('+5 minutes'), '  ', 'hash-placeholder');
    }

    public function testRejectsAnEmptyPasswordHash(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new MemberAccessToken('id-1', 'erika@example.test', str_repeat('a', 64), new \DateTimeImmutable('+5 minutes'), 'Bad-001', '  ');
    }

    public function testIsNotExpiredBeforeTheExpiryMoment(): void
    {
        $token = new MemberAccessToken('id-1', 'erika@example.test', str_repeat('a', 64), new \DateTimeImmutable('2026-06-01T10:05:00+02:00'), 'Bad-001', 'hash-placeholder');

        self::assertFalse($token->isExpired(new \DateTimeImmutable('2026-06-01T10:04:59+02:00')));
    }

    public function testIsExpiredAtOrAfterTheExpiryMoment(): void
    {
        $token = new MemberAccessToken('id-1', 'erika@example.test', str_repeat('a', 64), new \DateTimeImmutable('2026-06-01T10:05:00+02:00'), 'Bad-001', 'hash-placeholder');

        self::assertTrue($token->isExpired(new \DateTimeImmutable('2026-06-01T10:05:00+02:00')));
        self::assertTrue($token->isExpired(new \DateTimeImmutable('2026-06-01T10:05:01+02:00')));
    }
}
