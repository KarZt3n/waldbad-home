<?php

namespace App\Tests\Unit\Logic\Membership\ContributionRate\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\ContributionRate\Model\PendingContributionRateChange;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class ContributionRateTest extends TestCase
{
    public function testRejectsAnEmptyPendingLabel(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        $this->pending(label: '  ');
    }

    public function testRejectsANegativePendingAmount(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        $this->pending(amountCents: -100);
    }

    public function testRejectsAnInvalidPendingAgeRange(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        $this->pending(minAge: 20, maxAge: 10);
    }

    public function testHasNoPendingChangeByDefault(): void
    {
        $rate = $this->rate(null);

        self::assertFalse($rate->hasPendingChange());
        self::assertFalse($rate->isPendingChangeDue(new \DateTimeImmutable('2027-01-01')));
    }

    public function testPendingChangeIsDueOnOrAfterItsDate(): void
    {
        $rate = $this->rate($this->pending(validFrom: new \DateTimeImmutable('2027-01-01')));

        self::assertTrue($rate->hasPendingChange());
        self::assertFalse($rate->isPendingChangeDue(new \DateTimeImmutable('2026-12-31')));
        self::assertTrue($rate->isPendingChangeDue(new \DateTimeImmutable('2027-01-01')));
        self::assertTrue($rate->isPendingChangeDue(new \DateTimeImmutable('2027-06-01')));
    }

    /**
     * Kernstück des Umbaus: `applyPendingChange()` übernimmt den **gesamten** geplanten
     * Datensatz — nicht nur den Betrag —, z. B. eine gleichzeitige Erhöhung der
     * Arbeitseinsatz-Pauschale UND der unteren Altersgrenze.
     */
    public function testApplyPendingChangeMakesTheWholePendingRecordTheCurrentOne(): void
    {
        $rate = $this->rate($this->pending(
            label: 'Arbeitseinsatz (neu)',
            amountCents: 2000,
            minAge: 10,
            maxAge: 70,
            validFrom: new \DateTimeImmutable('2027-01-01'),
        ));

        $applied = $rate->applyPendingChange();

        self::assertSame('Arbeitseinsatz (neu)', $applied->label);
        self::assertSame(2000, $applied->amountCents);
        self::assertSame(10, $applied->minAge);
        self::assertSame(70, $applied->maxAge);
        self::assertNull($applied->pending);
        self::assertFalse($applied->hasPendingChange());
        self::assertSame($rate->id, $applied->id);
        self::assertSame($rate->category, $applied->category);
    }

    /**
     * Ohne geplante Änderung ist `applyPendingChange()` ein No-op — schützt vor versehentlichem
     * Aufruf außerhalb von `ContributionRateManager::applyDuePendingChange()`.
     */
    public function testApplyPendingChangeWithoutAPendingChangeIsANoOp(): void
    {
        $rate = $this->rate(null);

        self::assertSame($rate->amountCents, $rate->applyPendingChange()->amountCents);
    }

    private function rate(?PendingContributionRateChange $pending): ContributionRate
    {
        return new ContributionRate(
            id: 'rate-1',
            category: ContributionCategory::WorkAssignmentSurcharge,
            label: 'Arbeitseinsatz',
            amountCents: 1500,
            period: PaymentInterval::Yearly,
            minAge: 8,
            maxAge: 65,
            pending: $pending,
        );
    }

    private function pending(
        string $label = 'Arbeitseinsatz (neu)',
        int $amountCents = 2000,
        ?int $minAge = 10,
        ?int $maxAge = 70,
        \DateTimeImmutable $validFrom = new \DateTimeImmutable('2027-01-01'),
    ): PendingContributionRateChange {
        return new PendingContributionRateChange(
            label: $label,
            amountCents: $amountCents,
            period: PaymentInterval::Yearly,
            personGroup: null,
            minAge: $minAge,
            maxAge: $maxAge,
            validFrom: $validFrom,
        );
    }
}
