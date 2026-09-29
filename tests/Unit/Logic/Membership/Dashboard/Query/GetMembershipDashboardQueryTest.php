<?php

namespace App\Tests\Unit\Logic\Membership\Dashboard\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\ContributionRate\Model\PersonGroup;
use App\Logic\Membership\Dashboard\Dto\ContributionRateCount;
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

        $result = (new GetMembershipDashboardQuery($members, $applications, $this->createStub(ContributionRateManagerInterface::class), $clock))->execute();

        self::assertSame(5, $result->totalMembers);
        self::assertSame(1, $result->leavingAtYearEnd);
        self::assertSame(1, $result->leftLastYearEnd);
    }

    /**
     * Familie = aktives, selbstzahlendes Hauptmitglied (auch mehrere unter derselben Hauptnummer),
     * Einzelmitgliedschaft = Hauptnummer mit genau einer aktiven Person. Ausgetretene Mitglieder
     * zählen nicht mit — auch nicht als Rest eines Haushalts. Das Alter
     * ist das heutige Alter: wer am 31.12.2005 geboren ist, ist am 29.09.2026 noch 20.
     */
    public function testCountsFamiliesIndividualMembershipsAndAgeGroupsOfActiveMembers(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([
            $this->member('f1', null, 'F', '1980-01-01', ContributionCategory::FamilyAdult, 1500, familyRole: FamilyRole::Head),
            // Zweites selbstzahlendes Hauptmitglied unter derselben Hauptnummer → eigene Familie.
            $this->member('g1', null, 'F', '1982-01-01', ContributionCategory::FamilyAdult, null, familyRole: FamilyRole::Head),
            // Hauptmitglied, für das ein anderes Mitglied zahlt → keine eigene Familie.
            $this->member('h1', null, 'H', '1985-01-01', ContributionCategory::FamilyAdult, null, familyRole: FamilyRole::Head, payerMemberId: 'f1'),
            $this->member('f2', null, 'F', '2015-01-01', ContributionCategory::FamilyChildPaying, 1500),
            $this->member('e1', null, 'E', '2005-12-31', ContributionCategory::IndividualSenior, 1500),
            $this->member('e2', null, 'E2', '2010-01-01', ContributionCategory::IndividualJunior, null),
            // Ehemals Familie, eine Person ausgetreten → nur noch Einzelmitgliedschaft.
            $this->member('x1', null, 'X', '1970-01-01', null, null, false),
            // Beitragspflichtig, aber (noch) ohne berechneten Beitragssatz.
            $this->member('y1', null, 'Y', '1975-01-01', null, null),
            $this->member('x2', new \DateTimeImmutable('2025-12-31'), 'X', '1972-01-01', ContributionCategory::FamilyAdult, null),
        ]);

        $applications = $this->createStub(MembershipApplicationManagerInterface::class);
        $applications->method('list')->willReturn([]);

        $rates = $this->createStub(ContributionRateManagerInterface::class);
        $rates->method('list')->willReturn([
            new ContributionRate('r1', ContributionCategory::IndividualJunior, 'Einzel bis 21', 3000, PaymentInterval::Yearly),
            new ContributionRate('r2', ContributionCategory::IndividualSenior, 'Einzel ab 21', 5000, PaymentInterval::Yearly),
            new ContributionRate('r3', ContributionCategory::FamilyAdult, 'Familie Erwachsene', 4000, PaymentInterval::Yearly),
            new ContributionRate('r4', ContributionCategory::FamilyChildPaying, 'Familie Kind', 3000, PaymentInterval::Yearly),
            new ContributionRate('r5', ContributionCategory::WorkAssignmentSurcharge, 'Arbeitseinsatz', 1500, PaymentInterval::Yearly),
            new ContributionRate('r6', null, 'Beitrittsgebühr', 1000, PaymentInterval::Once, PersonGroup::Individual),
        ]);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-29'));

        $result = (new GetMembershipDashboardQuery($members, $applications, $rates, $clock))->execute();

        self::assertSame(2, $result->families);
        self::assertSame(5, $result->individualMemberships);
        self::assertSame(5, $result->adults);
        self::assertSame(3, $result->minors);
        self::assertSame(
            [
                ['Einzel bis 21', 1],
                ['Einzel ab 21', 1],
                ['Familie Erwachsene', 3],
                ['Familie Kind', 1],
                ['Arbeitseinsatz', 3],
                ['Ohne Beitragssatz (nicht beitragspflichtig)', 1],
                ['Ohne Beitragssatz', 1],
            ],
            array_map(static fn (ContributionRateCount $rate): array => [$rate->label, $rate->count], $result->contributionRateCounts),
        );
    }

    private function member(
        string $id,
        ?\DateTimeImmutable $leftAt,
        ?string $primaryMemberNumber = null,
        string $birthDate = '1990-01-01',
        ?ContributionCategory $contributionCategory = null,
        ?int $workAssignmentSurchargeCents = null,
        bool $contributionLiable = true,
        FamilyRole $familyRole = FamilyRole::None,
        ?string $payerMemberId = null,
    ): Member {
        return new Member(
            id: $id,
            memberNumber: $id,
            primaryMemberNumber: $primaryMemberNumber ?? $id,
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: 'Max',
            birthDate: new \DateTimeImmutable($birthDate),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: $familyRole,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: $leftAt,
            function: MemberFunction::Member,
            accountHolder: 'Max Muster',
            iban: 'DE89370400440532013000',
            bankName: null,
            mandateReference: $id,
            paymentMethod: PaymentMethod::SepaDirectDebit,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: $payerMemberId === null ? PayerType::SelfPayer : PayerType::OtherMember,
            payerMemberId: $payerMemberId,
            nextBookingMonth: 3,
            nextBookingYear: 2027,
            contributionCategory: $contributionCategory,
            contributionAmountCents: $contributionCategory === null ? null : 3000,
            workAssignmentSurchargeCents: $workAssignmentSurchargeCents,
            remarks: [],
            oneTimeCharges: [],
            version: 1,
            contributionLiable: $contributionLiable,
        );
    }
}
