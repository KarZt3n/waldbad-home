<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Service;

use App\Logic\Event\HelpRequest\Service\ParticipationTimeRounding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParticipationTimeRoundingTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function checkInTimes(): iterable
    {
        yield 'volle Stunde' => ['10:00', '10:00'];
        yield '10:05 abgerundet' => ['10:05', '10:00'];
        yield '10:14 abgerundet' => ['10:14', '10:00'];
        yield '10:15 bleibt' => ['10:15', '10:15'];
        yield '10:16 abgerundet' => ['10:16', '10:15'];
        yield '10:25 abgerundet' => ['10:25', '10:15'];
        yield '10:59 abgerundet' => ['10:59', '10:45'];
    }

    #[DataProvider('checkInTimes')]
    public function testCheckInRoundsDownToQuarterHour(string $now, string $expected): void
    {
        self::assertSame($expected, (new ParticipationTimeRounding())->checkInTime($this->berlin($now)));
    }

    /** @return iterable<string, array{string, string}> */
    public static function checkOutTimes(): iterable
    {
        yield 'volle Viertelstunde bleibt' => ['13:00', '13:00'];
        yield '13:01 aufgerundet' => ['13:01', '13:15'];
        yield '13:05 aufgerundet' => ['13:05', '13:15'];
        yield '13:15 bleibt' => ['13:15', '13:15'];
        yield '13:46 in die nächste Stunde' => ['13:46', '14:00'];
    }

    #[DataProvider('checkOutTimes')]
    public function testCheckOutRoundsUpEveryStartedQuarterHour(string $now, string $expected): void
    {
        self::assertSame($expected, (new ParticipationTimeRounding())->checkOutTime($this->berlin($now), '10:00'));
    }

    public function testCheckOutRightAfterCheckInLastsAtLeastOneQuarterHour(): void
    {
        self::assertSame('10:15', (new ParticipationTimeRounding())->checkOutTime($this->berlin('10:05'), '10:00'));
    }

    public function testCheckOutAfterMidnightIsCappedToEndOfDay(): void
    {
        self::assertSame('23:59', (new ParticipationTimeRounding())->checkOutTime($this->berlin('23:50'), '22:00'));
    }

    public function testUsesGermanLocalTimeRegardlessOfServerTimezone(): void
    {
        $utc = new \DateTimeImmutable('2026-07-04 08:07', new \DateTimeZone('UTC'));

        self::assertSame('10:00', (new ParticipationTimeRounding())->checkInTime($utc));
    }

    private function berlin(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-07-04 '.$time, new \DateTimeZone('Europe/Berlin'));
    }
}
