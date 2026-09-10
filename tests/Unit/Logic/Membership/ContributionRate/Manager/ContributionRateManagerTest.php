<?php

namespace App\Tests\Unit\Logic\Membership\ContributionRate\Manager;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\ContributionRate\ContributionRateProcessorInterface;
use App\Logic\Membership\ContributionRate\ContributionRateProviderInterface;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManager;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateSettingsManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\ContributionRate\Model\ContributionRateSettings;
use App\Logic\Membership\ContributionRate\Model\PendingContributionRateChange;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

/**
 * Deckt das lazy „Pending → aktiv"-Vorrücken ab (siehe `ContributionRate::$pending`): dieses
 * Projekt hat keine Scheduler-Infrastruktur, daher übernimmt `ContributionRateManager` das beim
 * Lesen statt über einen Cronjob.
 */
final class ContributionRateManagerTest extends TestCase
{
    private const string NOW = '2027-01-01T10:00:00+02:00';

    public function testListLeavesARateWithoutAPendingChangeUntouched(): void
    {
        $rate = $this->rate(null);
        $provider = $this->createStub(ContributionRateProviderInterface::class);
        $provider->method('findAll')->willReturn([$rate]);
        $processor = $this->createMock(ContributionRateProcessorInterface::class);
        $processor->expects(self::never())->method('save');
        $settings = $this->createMock(ContributionRateSettingsManagerInterface::class);
        $settings->expects(self::never())->method('save');

        $result = $this->manager($provider, $processor, $settings)->list();

        self::assertSame($rate, $result[0]);
    }

    public function testListLeavesAPendingChangeUntouchedBeforeItsDate(): void
    {
        $rate = $this->rate(new \DateTimeImmutable('2027-06-01'));
        $provider = $this->createStub(ContributionRateProviderInterface::class);
        $provider->method('findAll')->willReturn([$rate]);
        $processor = $this->createMock(ContributionRateProcessorInterface::class);
        $processor->expects(self::never())->method('save');
        $settings = $this->createMock(ContributionRateSettingsManagerInterface::class);
        $settings->expects(self::never())->method('save');

        $result = $this->manager($provider, $processor, $settings)->list();

        self::assertSame(5000, $result[0]->amountCents);
        self::assertTrue($result[0]->hasPendingChange());
    }

    /**
     * Kernstück: Ist das geplante Datum erreicht, wird der **gesamte** geplante Datensatz
     * übernommen (nicht nur der Betrag — hier zusätzlich auch die Altersspanne), die geplante
     * Änderung gelöscht, und das Datum wird zum neuen gemeinsamen `ContributionRateSettings::$validFrom`.
     */
    public function testListAppliesADuePendingChangeAndAdvancesTheSharedValidFrom(): void
    {
        $rate = $this->rate(new \DateTimeImmutable('2027-01-01'));
        $provider = $this->createStub(ContributionRateProviderInterface::class);
        $provider->method('findAll')->willReturn([$rate]);

        $savedRate = null;
        $processor = $this->createMock(ContributionRateProcessorInterface::class);
        $processor->expects(self::once())->method('save')->willReturnCallback(function (ContributionRate $rate) use (&$savedRate) {
            $savedRate = $rate;

            return $rate;
        });

        $savedSettings = null;
        $settings = $this->createMock(ContributionRateSettingsManagerInterface::class);
        $settings->method('get')->willReturn(new ContributionRateSettings(null));
        $settings->expects(self::once())->method('save')->willReturnCallback(function (ContributionRateSettings $settings) use (&$savedSettings) {
            $savedSettings = $settings;

            return $settings;
        });

        $result = $this->manager($provider, $processor, $settings)->list();

        self::assertSame(6000, $result[0]->amountCents);
        self::assertSame(10, $result[0]->minAge);
        self::assertFalse($result[0]->hasPendingChange());
        self::assertNotNull($savedRate);
        self::assertSame(6000, $savedRate->amountCents);
        self::assertSame(10, $savedRate->minAge);
        self::assertNotNull($savedSettings);
        self::assertEquals(new \DateTimeImmutable('2027-01-01'), $savedSettings->validFrom);
    }

    /**
     * Das gemeinsame „gültig ab" wird nur vorgerückt, nie zurückgesetzt — ein bereits jüngeres
     * Datum (z. B. von Hand im Admin gesetzt) bleibt unangetastet.
     */
    public function testDoesNotRegressTheSharedValidFromWhenItIsAlreadyNewer(): void
    {
        $rate = $this->rate(new \DateTimeImmutable('2027-01-01'));
        $provider = $this->createStub(ContributionRateProviderInterface::class);
        $provider->method('findAll')->willReturn([$rate]);
        $processor = $this->createStub(ContributionRateProcessorInterface::class);
        $processor->method('save')->willReturnArgument(0);

        $settings = $this->createMock(ContributionRateSettingsManagerInterface::class);
        $settings->method('get')->willReturn(new ContributionRateSettings(new \DateTimeImmutable('2027-06-01')));
        $settings->expects(self::never())->method('save');

        $this->manager($provider, $processor, $settings)->list();
    }

    public function testGetAppliesADuePendingChange(): void
    {
        $rate = $this->rate(new \DateTimeImmutable('2027-01-01'));
        $provider = $this->createStub(ContributionRateProviderInterface::class);
        $provider->method('find')->willReturn($rate);
        $processor = $this->createStub(ContributionRateProcessorInterface::class);
        $processor->method('save')->willReturnArgument(0);
        $settings = $this->createStub(ContributionRateSettingsManagerInterface::class);
        $settings->method('get')->willReturn(new ContributionRateSettings(null));

        $result = $this->manager($provider, $processor, $settings)->get('rate-1');

        self::assertSame(6000, $result->amountCents);
    }

    public function testFindByCategoryAppliesADuePendingChange(): void
    {
        $rate = $this->rate(new \DateTimeImmutable('2027-01-01'));
        $provider = $this->createStub(ContributionRateProviderInterface::class);
        $provider->method('findByCategory')->willReturn($rate);
        $processor = $this->createStub(ContributionRateProcessorInterface::class);
        $processor->method('save')->willReturnArgument(0);
        $settings = $this->createStub(ContributionRateSettingsManagerInterface::class);
        $settings->method('get')->willReturn(new ContributionRateSettings(null));

        $result = $this->manager($provider, $processor, $settings)->findByCategory(ContributionCategory::IndividualSenior);

        self::assertNotNull($result);
        self::assertSame(6000, $result->amountCents);
    }

    public function testSaveImmediatelyAppliesPastAndTodayChanges(): void
    {
        foreach (['2026-01-01', '2027-01-01'] as $date) {
            $rate = $this->rate(new \DateTimeImmutable($date));
            $provider = $this->createStub(ContributionRateProviderInterface::class);
            $processor = $this->createStub(ContributionRateProcessorInterface::class);
            $processor->method('save')->willReturnArgument(0);
            $settings = $this->createMock(ContributionRateSettingsManagerInterface::class);
            $settings->method('get')->willReturn(new ContributionRateSettings(null));
            $settings->expects(self::once())->method('save')
                ->with(self::callback(static fn (ContributionRateSettings $value): bool => $value->validFrom?->format('Y-m-d') === $date))
                ->willReturnArgument(0);

            $saved = $this->manager($provider, $processor, $settings)->save($rate);

            self::assertSame(6000, $saved->amountCents);
            self::assertSame(10, $saved->minAge);
            self::assertNull($saved->pending);
        }
    }

    public function testSaveKeepsFutureVersionPendingAndCurrentDateUnchanged(): void
    {
        $rate = $this->rate(new \DateTimeImmutable('2027-06-01'));
        $provider = $this->createStub(ContributionRateProviderInterface::class);
        $processor = $this->createMock(ContributionRateProcessorInterface::class);
        $processor->expects(self::once())->method('save')->with($rate)->willReturnArgument(0);
        $settings = $this->createMock(ContributionRateSettingsManagerInterface::class);
        $settings->expects(self::never())->method('save');

        $saved = $this->manager($provider, $processor, $settings)->save($rate);

        self::assertSame(5000, $saved->amountCents);
        self::assertNull($saved->minAge);
        self::assertSame($rate->pending, $saved->pending);
    }

    private function manager(
        ContributionRateProviderInterface $provider,
        ContributionRateProcessorInterface $processor,
        ContributionRateSettingsManagerInterface $settings,
    ): ContributionRateManager {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable(self::NOW));

        return new ContributionRateManager($provider, $processor, $settings, $clock);
    }

    private function rate(?\DateTimeImmutable $pendingValidFrom): ContributionRate
    {
        return new ContributionRate(
            id: 'rate-1',
            category: ContributionCategory::IndividualSenior,
            label: 'Einzelperson über 21 Jahre',
            amountCents: 5000,
            period: PaymentInterval::Yearly,
            pending: $pendingValidFrom === null ? null : new PendingContributionRateChange(
                label: 'Einzelperson über 21 Jahre',
                amountCents: 6000,
                period: PaymentInterval::Yearly,
                personGroup: null,
                minAge: 10,
                maxAge: null,
                validFrom: $pendingValidFrom,
            ),
        );
    }
}
