<?php

namespace App\Tests\Unit\Logic\Membership\Member\Service;

use App\Logic\Common\Exception\BusinessRuleViolationException;
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
use App\Logic\Membership\Member\Service\MemberContributionCalculator;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class MemberContributionCalculatorTest extends TestCase
{
    /**
     * Standard-Beitragssätze mit denselben Altersspannen wie die Seed-Daten der Migration:
     * [amountCents, minAge, maxAge].
     */
    private const array DEFAULT_RATES = [
        'individual_junior' => [3000, null, 20],
        'individual_senior' => [5000, 21, null],
        'family_adult' => [4000, null, null],
        'family_child_paying' => [3000, 4, 20],
        'family_child_exempt' => [0, null, 3],
        'work_assignment_surcharge' => [1500, 8, 65],
    ];

    public function testIndividualUpToTwentyPaysJuniorRate(): void
    {
        $calculator = $this->calculator();
        $candidate = $this->member(birthDate: '2015-01-01');

        $outcome = $calculator->calculate($candidate, [], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::IndividualJunior, $outcome->category);
        self::assertSame(3000, $outcome->amountCents);
    }

    public function testIndividualFromTwentyOnePaysSeniorRate(): void
    {
        $calculator = $this->calculator();
        $candidate = $this->member(birthDate: '1990-01-01');

        $outcome = $calculator->calculate($candidate, [], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::IndividualSenior, $outcome->category);
        self::assertSame(5000, $outcome->amountCents);
    }

    public function testFamilyHeadWithQualifyingChildGetsFamilyDiscount(): void
    {
        $calculator = $this->calculator();
        $head = $this->member(birthDate: '1985-01-01', familyRole: FamilyRole::Head, id: 'head');
        $child = $this->member(birthDate: '2015-01-01', familyRole: FamilyRole::Child, id: 'child', primaryMemberNumber: 'M-0001');

        $outcome = $calculator->calculate($head, [$child], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::FamilyAdult, $outcome->category);
        self::assertSame(4000, $outcome->amountCents);
    }

    public function testFamilyHeadWithoutQualifyingChildFallsBackToIndividualRate(): void
    {
        $calculator = $this->calculator();
        $head = $this->member(birthDate: '1985-01-01', familyRole: FamilyRole::Head, id: 'head');
        $adultChild = $this->member(birthDate: '1995-01-01', familyRole: FamilyRole::Child, id: 'child');

        $outcome = $calculator->calculate($head, [$adultChild], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::IndividualSenior, $outcome->category);
    }

    public function testFamilyHeadFallsBackToIndividualWhenFamilyAdultRateWasDeleted(): void
    {
        $calculator = $this->calculator(omit: ['family_adult']);
        $head = $this->member(birthDate: '1985-01-01', familyRole: FamilyRole::Head, id: 'head');
        $child = $this->member(birthDate: '2015-01-01', familyRole: FamilyRole::Child, id: 'child');

        $outcome = $calculator->calculate($head, [$child], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::IndividualSenior, $outcome->category);
    }

    public function testChildUnderFourIsExemptRegardlessOfOrdinal(): void
    {
        $calculator = $this->calculator();
        $child = $this->member(birthDate: '2023-01-01', familyRole: FamilyRole::Child, id: 'first-child');

        $outcome = $calculator->calculate($child, [], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::FamilyChildExempt, $outcome->category);
        self::assertSame(0, $outcome->amountCents);
    }

    public function testFirstAndSecondChildPayTheChildRate(): void
    {
        $calculator = $this->calculator();
        $firstChild = $this->member(birthDate: '2012-01-01', familyRole: FamilyRole::Child, id: 'first');
        $secondChild = $this->member(birthDate: '2014-01-01', familyRole: FamilyRole::Child, id: 'second');

        $outcomeFirst = $calculator->calculate($firstChild, [$secondChild], new \DateTimeImmutable('2026-06-01'));
        $outcomeSecond = $calculator->calculate($secondChild, [$firstChild], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::FamilyChildPaying, $outcomeFirst->category);
        self::assertSame(ContributionCategory::FamilyChildPaying, $outcomeSecond->category);
    }

    public function testChildOverTwentyOneBecomesIndividualMemberAutomatically(): void
    {
        $calculator = $this->calculator();
        $formerChild = $this->member(birthDate: '2000-01-01', familyRole: FamilyRole::Child, id: 'former-child'); // 26 Jahre

        $outcome = $calculator->calculate($formerChild, [], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::IndividualSenior, $outcome->category);
        self::assertSame(5000, $outcome->amountCents);
    }

    public function testTwoAdultsWithSameHouseholdWithoutQualifyingChildGetNoFamilyDiscount(): void
    {
        $calculator = $this->calculator();
        $parentOne = $this->member(birthDate: '1980-01-01', familyRole: FamilyRole::Head, id: 'parent-one');
        $parentTwo = $this->member(birthDate: '1982-01-01', familyRole: FamilyRole::Partner, id: 'parent-two');
        $formerChild = $this->member(birthDate: '2000-01-01', familyRole: FamilyRole::Child, id: 'former-child'); // 26 Jahre, längst kein Kind mehr

        $outcomeOne = $calculator->calculate($parentOne, [$parentTwo, $formerChild], new \DateTimeImmutable('2026-06-01'));
        $outcomeTwo = $calculator->calculate($parentTwo, [$parentOne, $formerChild], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::IndividualSenior, $outcomeOne->category);
        self::assertSame(ContributionCategory::IndividualSenior, $outcomeTwo->category);
    }

    public function testThrowsForAChildWhenBothChildRatesAreDeletedAndAgeCannotBeResolved(): void
    {
        $calculator = $this->calculator(omit: ['family_child_exempt', 'family_child_paying']);
        $child = $this->member(birthDate: '2015-01-01', familyRole: FamilyRole::Child, id: 'child');

        $this->expectException(BusinessRuleViolationException::class);
        $calculator->calculate($child, [], new \DateTimeImmutable('2026-06-01'));
    }

    public function testThirdChildIsExemptRegardlessOfAge(): void
    {
        $calculator = $this->calculator();
        $first = $this->member(birthDate: '2010-01-01', familyRole: FamilyRole::Child, id: 'first');
        $second = $this->member(birthDate: '2012-01-01', familyRole: FamilyRole::Child, id: 'second');
        $third = $this->member(birthDate: '2014-01-01', familyRole: FamilyRole::Child, id: 'third');

        $outcome = $calculator->calculate($third, [$first, $second], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::FamilyChildExempt, $outcome->category);
    }

    public function testWorkAssignmentSurchargeAppliesWithinConfiguredAgeRange(): void
    {
        $calculator = $this->calculator();
        $candidate = $this->member(birthDate: '1990-01-01'); // 36 Jahre am Stichtag

        $outcome = $calculator->calculate($candidate, [], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(1500, $outcome->workAssignmentSurchargeCents);
    }

    public function testWorkAssignmentSurchargeDoesNotApplyBelowMinimumAge(): void
    {
        $calculator = $this->calculator();
        $candidate = $this->member(birthDate: '2020-01-01'); // 6 Jahre am Stichtag

        $outcome = $calculator->calculate($candidate, [], new \DateTimeImmutable('2026-06-01'));

        self::assertNull($outcome->workAssignmentSurchargeCents);
    }

    public function testWorkAssignmentSurchargeDoesNotApplyAboveMaximumAge(): void
    {
        $calculator = $this->calculator();
        $candidate = $this->member(birthDate: '1955-01-01'); // 71 Jahre am Stichtag

        $outcome = $calculator->calculate($candidate, [], new \DateTimeImmutable('2026-06-01'));

        self::assertNull($outcome->workAssignmentSurchargeCents);
    }

    public function testWorkAssignmentSurchargeIsSkippedWhenRateWasDeleted(): void
    {
        $calculator = $this->calculator(omit: ['work_assignment_surcharge']);
        $candidate = $this->member(birthDate: '1990-01-01');

        $outcome = $calculator->calculate($candidate, [], new \DateTimeImmutable('2026-06-01'));

        self::assertNull($outcome->workAssignmentSurchargeCents);
    }

    public function testThrowsWhenNoIndividualRateMatchesTheMembersAge(): void
    {
        $calculator = $this->calculator(omit: ['individual_junior', 'individual_senior']);
        $candidate = $this->member(birthDate: '1990-01-01');

        $this->expectException(BusinessRuleViolationException::class);
        $calculator->calculate($candidate, [], new \DateTimeImmutable('2026-06-01'));
    }

    /**
     * @param list<string> $omit Kategorien, die wie gelöscht behandelt werden (findByCategory liefert null).
     * @param array<string, array{0: int, 1: ?int, 2: ?int}> $overrides Kategorie => [amountCents, minAge, maxAge]
     */
    private function calculator(array $omit = [], array $overrides = []): MemberContributionCalculator
    {
        $rates = array_merge(self::DEFAULT_RATES, $overrides);
        foreach ($omit as $category) {
            unset($rates[$category]);
        }

        $manager = $this->createStub(ContributionRateManagerInterface::class);
        $manager->method('findByCategory')->willReturnCallback(
            static function (ContributionCategory $category) use ($rates): ?ContributionRate {
                if (!isset($rates[$category->value])) {
                    return null;
                }
                [$amountCents, $minAge, $maxAge] = $rates[$category->value];

                return new ContributionRate(
                    id: $category->value,
                    category: $category,
                    label: $category->value,
                    amountCents: $amountCents,
                    period: PaymentInterval::Yearly,
                    minAge: $minAge,
                    maxAge: $maxAge,
                );
            },
        );

        return new MemberContributionCalculator($manager);
    }

    private function member(
        string $birthDate,
        FamilyRole $familyRole = FamilyRole::None,
        string $id = 'member',
        string $primaryMemberNumber = 'M-0001',
    ): Member {
        return new Member(
            id: $id,
            memberNumber: 'M-0001',
            primaryMemberNumber: $primaryMemberNumber,
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
            joinedAt: new \DateTimeImmutable('2026-01-01'),
            leftAt: null,
            active: true,
            function: MemberFunction::Member,
            accountHolder: 'Max Muster',
            iban: 'DE02120300000000202051',
            bankName: null,
            mandateReference: 'M-0001',
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
