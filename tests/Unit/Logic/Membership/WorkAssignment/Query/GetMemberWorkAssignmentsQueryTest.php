<?php

namespace App\Tests\Unit\Logic\Membership\WorkAssignment\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\HelpRequest\Query\GetMemberWorkedMinutesQuery;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\MemberAccess\Service\WorkAssignmentCreditCalculator;
use App\Logic\Membership\MemberAccess\Service\WorkAssignmentCreditConfig;
use App\Logic\Membership\PaymentInterval;
use App\Logic\Membership\WorkAssignment\MemberWorkAssignmentProviderInterface;
use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentInterval;
use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentParticipation;
use App\Logic\Membership\WorkAssignment\Query\GetMemberWorkAssignmentsQuery;
use PHPUnit\Framework\TestCase;

final class GetMemberWorkAssignmentsQueryTest extends TestCase
{
    /**
     * Das laufende Jahr erscheint immer (auch ohne Einsätze), frühere Jahre nur mit Einsätzen; neuestes
     * zuerst. Die Gutschrift wird je Beitragsjahr für den ganzen Haushalt berechnet.
     */
    public function testGroupsHouseholdParticipationsByContributionYear(): void
    {
        $household = [$this->member('parent', 'Erika', 1500), $this->member('child', 'Max', null)];
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('get')->willReturn($household[1]);
        $members->method('findByPrimaryMemberNumber')->willReturn($household);

        $participations = [
            $this->participation('child', 'Sommerfest', '2025-07-12', 120),
            $this->participation('parent', 'Frühjahrsputz', '2025-04-05', 180),
            $this->participation('parent', 'Aufbau', '2024-05-01', 60),
        ];
        $provider = $this->createMock(MemberWorkAssignmentProviderInterface::class);
        $provider->expects(self::once())->method('findForMembers')->with(['parent', 'child'])->willReturn($participations);

        $years = (new GetMemberWorkAssignmentsQuery($members, $provider, $this->calculator(), $this->clock('2026-03-01')))->execute('child');

        self::assertSame([2026, 2025, 2024], array_map(static fn ($year): int => $year->year, $years));
        self::assertSame([], $years[0]->participations);
        self::assertSame(['Sommerfest', 'Frühjahrsputz'], array_map(static fn ($participation): string => $participation->eventTitle, $years[1]->participations));
        self::assertSame('Max Musterfrau', $years[1]->memberNames['child']);

        // 300 Minuten bei 3 €/Std. (15 € Zuschlag / 5 Std.) = 15 €, gedeckelt auf den Zuschlag des Haushalts.
        self::assertSame(300, $years[1]->credit->workedMinutes);
        self::assertSame(1500, $years[1]->credit->creditCents);
        self::assertSame(60, $years[2]->credit->workedMinutes);
        self::assertSame(300, $years[2]->credit->creditCents);
        self::assertSame(0, $years[0]->credit->creditCents);
    }

    private function calculator(): WorkAssignmentCreditCalculator
    {
        $rates = $this->createStub(ContributionRateManagerInterface::class);
        $rates->method('findByCategory')->willReturn(new ContributionRate(
            'rate-1',
            ContributionCategory::WorkAssignmentSurcharge,
            'Arbeitseinsatz-Zuschlag',
            1500,
            PaymentInterval::Yearly,
        ));
        $minutesByYear = ['2024' => 60, '2025' => 300];
        $hoursQuery = $this->createStub(GetMemberWorkedMinutesQuery::class);
        $hoursQuery->method('totalMinutes')->willReturnCallback(
            static fn (array $memberIds, \DateTimeImmutable $from): int => $minutesByYear[$from->format('Y')] ?? 0,
        );

        return new WorkAssignmentCreditCalculator($rates, $hoursQuery, new WorkAssignmentCreditConfig(
            ['from' => '2026-01-01', 'to' => '2027-01-01'],
            [['valid_from' => '2024-01-01', 'required_hours' => 5]],
        ));
    }

    private function clock(string $today): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable($today));

        return $clock;
    }

    private function participation(string $memberId, string $title, string $date, int $minutes): WorkAssignmentParticipation
    {
        return new WorkAssignmentParticipation($memberId, $title, $date, '09:00', $minutes, [new WorkAssignmentInterval('09:00', '10:00')]);
    }

    private function member(string $id, string $firstName, ?int $workAssignmentSurchargeCents): Member
    {
        return new Member(
            id: $id,
            memberNumber: 'Bad-001',
            primaryMemberNumber: 'Bad-001',
            salutation: Salutation::Ms,
            lastName: 'Musterfrau',
            firstName: $firstName,
            birthDate: new \DateTimeImmutable('1990-01-01'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: FamilyRole::Head,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            function: MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: PaymentMethod::BankTransfer,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: PayerType::SelfPayer,
            payerMemberId: null,
            nextBookingMonth: 1,
            nextBookingYear: 2027,
            contributionCategory: null,
            contributionAmountCents: 5000,
            workAssignmentSurchargeCents: $workAssignmentSurchargeCents,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
        );
    }
}
