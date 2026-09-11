<?php

namespace App\Tests\Unit\Logic\Membership\MemberAccess\Service;

use App\Logic\Event\HelpRequest\Query\GetMemberWorkedMinutesQuery;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
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
use PHPUnit\Framework\TestCase;

final class WorkAssignmentCreditCalculatorTest extends TestCase
{
    /**
     * Nutzer-Beispiel: Familie mit 2 Elternteilen und 2 im Arbeitseinsatz-Alter zuschlagspflichtigen
     * Kindern (macht 4 Arbeitseinsätze à 15 €), ein drittes Kind ist zu jung und deshalb nicht
     * zuschlagspflichtig. Nur die Eltern haben zusammen 12 Stunden geleistet, die Kinder gar nicht —
     * Stunden sind innerhalb der Familie übertragbar, es zählt die Summe. Bei 15 €/5 Std = 3 €/Std
     * ergibt das 36 € Gutschrift, deutlich unter dem Deckel von 4 × 15 € = 60 €.
     */
    public function testCreditIsHoursTimesRateWhenBelowTheFamilysSurchargeCap(): void
    {
        $household = [
            $this->member('parent-1', 1500),
            $this->member('parent-2', 1500),
            $this->member('child-8', 1500),
            $this->member('child-13', 1500),
            $this->member('child-2', null),
        ];

        $calculator = $this->calculator($household, workedMinutes: 720, surchargeAmountCents: 1500, requiredHours: 5);

        $result = $calculator->calculate($household);

        self::assertSame(4, $result->liableMemberCount);
        self::assertSame(6000, $result->totalSurchargeCents);
        self::assertSame(5, $result->requiredHoursPerAssignment);
        self::assertSame(300, $result->creditPerHourCents);
        self::assertSame(720, $result->workedMinutes);
        self::assertSame(3600, $result->creditCents);
    }

    /**
     * Die Gutschrift darf den tatsächlich fälligen Arbeitseinsatz-Zuschlag der Familie nie
     * übersteigen, egal wie viele Stunden geleistet wurden.
     */
    public function testCreditIsCappedAtTheFamilysTotalSurcharge(): void
    {
        $household = [$this->member('parent-1', 1500)];

        $calculator = $this->calculator($household, workedMinutes: 600, surchargeAmountCents: 1500, requiredHours: 5);

        $result = $calculator->calculate($household);

        self::assertSame(1500, $result->totalSurchargeCents);
        self::assertSame(1500, $result->creditCents);
    }

    public function testNoLiableMembersMeansNoCreditAndNoDivisionByZero(): void
    {
        $household = [$this->member('senior-1', null)];

        $calculator = $this->calculator($household, workedMinutes: 0, surchargeAmountCents: 1500, requiredHours: 5);

        $result = $calculator->calculate($household);

        self::assertSame(0, $result->liableMemberCount);
        self::assertSame(0, $result->totalSurchargeCents);
        self::assertSame(0, $result->creditCents);
    }

    /**
     * @param list<Member> $household
     */
    private function calculator(array $household, int $workedMinutes, int $surchargeAmountCents, int $requiredHours): WorkAssignmentCreditCalculator
    {
        $rates = $this->createStub(ContributionRateManagerInterface::class);
        $rates->method('findByCategory')->willReturn(new ContributionRate(
            'rate-1',
            ContributionCategory::WorkAssignmentSurcharge,
            'Arbeitseinsatz-Zuschlag',
            $surchargeAmountCents,
            PaymentInterval::Yearly,
        ));

        $hoursQuery = $this->createStub(GetMemberWorkedMinutesQuery::class);
        $hoursQuery->method('totalMinutes')->willReturn($workedMinutes);

        $config = new WorkAssignmentCreditConfig(
            ['from' => '2026-01-01', 'to' => '2027-01-01'],
            [['valid_from' => '2026-01-01', 'required_hours' => $requiredHours]],
        );

        return new WorkAssignmentCreditCalculator($rates, $hoursQuery, $config);
    }

    private function member(string $id, ?int $workAssignmentSurchargeCents): Member
    {
        return new Member(
            id: $id,
            memberNumber: 'Bad-001',
            primaryMemberNumber: 'Bad-001',
            salutation: Salutation::Ms,
            lastName: 'Musterfrau',
            firstName: 'Erika',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: FamilyRole::Head,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            active: true,
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
