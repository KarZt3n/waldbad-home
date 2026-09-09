<?php

namespace App\Tests\Unit\Logic\Membership\Dashboard\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Dashboard\Query\GetMembershipDashboardQuery;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class GetMembershipDashboardQueryTest extends TestCase
{
    public function testCountsOnlyMembersLeavingOnTheThirtyFirstOfDecemberOfTheCurrentAndPreviousYear(): void
    {
        $leavingThisYearEnd = $this->member('a', leftAt: new \DateTimeImmutable('2026-12-31'));
        $leavingMidYear = $this->member('b', leftAt: new \DateTimeImmutable('2026-06-30'));
        $leftLastYearEnd = $this->member('c', leftAt: new \DateTimeImmutable('2025-12-31'));
        $leftTwoYearsAgoEnd = $this->member('e', leftAt: new \DateTimeImmutable('2024-12-31'));
        $stillMember = $this->member('d', leftAt: null);

        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([
            $leavingThisYearEnd, $leavingMidYear, $leftLastYearEnd, $leftTwoYearsAgoEnd, $stillMember,
        ]);

        $applications = $this->createStub(MembershipApplicationManagerInterface::class);
        $applications->method('list')->willReturn([]);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-09'));

        $result = (new GetMembershipDashboardQuery($members, $applications, $clock))->execute();

        self::assertSame(5, $result->totalMembers);
        self::assertSame(1, $result->leavingAtYearEnd);
        self::assertSame(1, $result->leftLastYearEnd);
    }

    private function member(string $id, ?\DateTimeImmutable $leftAt): Member
    {
        return new Member(
            id: $id,
            memberNumber: $id,
            primaryMemberNumber: $id,
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: 'Max',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: $leftAt,
            active: true,
            function: MemberFunction::Member,
            accountHolder: 'Max Muster',
            iban: 'DE89370400440532013000',
            bankName: null,
            mandateReference: $id,
            paymentMethod: PaymentMethod::SepaDirectDebit,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: PayerType::SelfPayer,
            payerMemberId: null,
            nextBookingMonth: 3,
            nextBookingYear: 2027,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 1,
        );
    }
}
