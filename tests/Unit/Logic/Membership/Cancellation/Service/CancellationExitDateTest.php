<?php

namespace App\Tests\Unit\Logic\Membership\Cancellation\Service;

use App\Logic\Membership\Cancellation\Service\CancellationExitDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CancellationExitDateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function submissions(): iterable
    {
        yield 'Jahresanfang' => ['2026-01-02 08:00', '2026-12-31'];
        yield 'letzter Tag der Frist' => ['2026-09-30 23:59', '2026-12-31'];
        yield 'einen Tag zu spät' => ['2026-10-01 00:00', '2027-12-31'];
        yield 'Silvester' => ['2026-12-31 12:00', '2027-12-31'];
    }

    #[DataProvider('submissions')]
    public function testExitIsAlwaysTheThirtyFirstOfDecemberWithThreeMonthsNotice(string $submittedAt, string $expected): void
    {
        self::assertSame($expected, CancellationExitDate::for(new \DateTimeImmutable($submittedAt))->format('Y-m-d'));
    }
}
