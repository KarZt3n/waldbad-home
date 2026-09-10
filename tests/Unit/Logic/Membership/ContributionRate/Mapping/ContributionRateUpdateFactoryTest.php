<?php

namespace App\Tests\Unit\Logic\Membership\ContributionRate\Mapping;

use App\Logic\Membership\ContributionRate\Dto\UpdateContributionRateRequest;
use App\Logic\Membership\ContributionRate\Mapping\ContributionRateUpdateFactory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\ContributionRate\Model\PersonGroup;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class ContributionRateUpdateFactoryTest extends TestCase
{
    public function testDatedUpdatePreservesCurrentValuesAndSchedulesEveryEditableField(): void
    {
        $current = new ContributionRate('rate', null, 'Bisher', 1000, PaymentInterval::Yearly, PersonGroup::Individual, 8, 65);
        $request = new UpdateContributionRateRequest(
            'rate', 'Geändert', 2000, PaymentInterval::Monthly, null, null, 70, null,
            new \DateTimeImmutable('2027-01-01'),
        );

        $result = (new ContributionRateUpdateFactory())->fromRequest($current, $request);

        self::assertSame('Bisher', $result->label);
        self::assertSame(1000, $result->amountCents);
        self::assertSame(PaymentInterval::Yearly, $result->period);
        self::assertSame(PersonGroup::Individual, $result->personGroup);
        self::assertSame(8, $result->minAge);
        self::assertSame(65, $result->maxAge);
        self::assertNotNull($result->pending);
        self::assertSame('Geändert', $result->pending->label);
        self::assertSame(2000, $result->pending->amountCents);
        self::assertSame(PaymentInterval::Monthly, $result->pending->period);
        self::assertNull($result->pending->personGroup);
        self::assertNull($result->pending->minAge);
        self::assertSame(70, $result->pending->maxAge);
        self::assertSame($request->validFrom, $result->pending->validFrom);
    }
}
