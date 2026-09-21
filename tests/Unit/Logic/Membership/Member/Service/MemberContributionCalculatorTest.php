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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testContributionExemptMemberIsChargedNothingRegardlessOfCategory(): void
    {
        $calculator = $this->calculator();
        $board = $this->member(birthDate: '1985-01-01', contributionLiable: false);

        $outcome = $calculator->calculate($board, [], new \DateTimeImmutable('2026-06-01'));

        self::assertNull($outcome->category);
        self::assertSame(0, $outcome->amountCents);
        self::assertNull($outcome->workAssignmentSurchargeCents);
    }

    public function testMemberWhoHasLeftIsChargedNothingRegardlessOfCategory(): void
    {
        $calculator = $this->calculator();
        $former = $this->member(birthDate: '1985-01-01', leftAt: '2026-05-01');

        $outcome = $calculator->calculate($former, [], new \DateTimeImmutable('2026-06-01'));

        self::assertNull($outcome->category);
        self::assertSame(0, $outcome->amountCents);
        self::assertNull($outcome->workAssignmentSurchargeCents);
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
        $parent = $this->member(birthDate: '1985-01-01', familyRole: FamilyRole::Head, id: 'parent');
        $child = $this->member(birthDate: '2023-01-01', familyRole: FamilyRole::Child, id: 'first-child');

        $outcome = $calculator->calculate($child, [$parent], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::FamilyChildExempt, $outcome->category);
        self::assertSame(0, $outcome->amountCents);
    }

    /**
     * Symmetrisch zu `testFamilyHeadWithoutQualifyingChildFallsBackToIndividualRate()`: ohne einen
     * eigenen, bereits erwachsenen Elternteil (Head/Partner) im Haushalt ist es keine „Familie" im
     * Sinne der Beitragsordnung — z. B. mehrere unter derselben Hauptnummer geführte Geschwister
     * ohne im System hinterlegten Elternteil zahlen den regulären Einzelpersonen-Satz statt des
     * Familienrabatts.
     */
    public function testChildWithoutQualifyingParentInHouseholdGetsIndividualRateInstead(): void
    {
        $calculator = $this->calculator();
        $sibling = $this->member(birthDate: '2013-01-01', familyRole: FamilyRole::Child, id: 'sibling');
        $child = $this->member(birthDate: '2011-01-01', familyRole: FamilyRole::Child, id: 'child');

        $outcome = $calculator->calculate($child, [$sibling], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::IndividualJunior, $outcome->category);
        self::assertSame(3000, $outcome->amountCents);
    }

    /**
     * Ein selbst noch minderjähriges „Hauptmitglied" (z. B. weil kein echter Elternteil im System
     * hinterlegt ist) darf nicht allein deshalb den Erwachsenen-Familienrabatt erhalten, weil der
     * Familienbeitragssatz keine eigene Altersuntergrenze konfiguriert hat (`family_adult` hat in
     * `DEFAULT_RATES` bewusst `minAge: null`).
     */
    public function testMinorFamilyHeadDoesNotGetFamilyAdultRateEvenWithoutConfiguredMinimumAge(): void
    {
        $calculator = $this->calculator();
        $minorHead = $this->member(birthDate: '2010-10-20', familyRole: FamilyRole::Head, id: 'minor-head');
        $sibling = $this->member(birthDate: '2013-01-01', familyRole: FamilyRole::Child, id: 'sibling');

        $outcome = $calculator->calculate($minorHead, [$sibling], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::IndividualJunior, $outcome->category);
        self::assertSame(3000, $outcome->amountCents);
    }

    public function testFirstAndSecondChildPayTheChildRate(): void
    {
        $calculator = $this->calculator();
        $parent = $this->member(birthDate: '1985-01-01', familyRole: FamilyRole::Head, id: 'parent');
        $firstChild = $this->member(birthDate: '2012-01-01', familyRole: FamilyRole::Child, id: 'first');
        $secondChild = $this->member(birthDate: '2014-01-01', familyRole: FamilyRole::Child, id: 'second');

        $outcomeFirst = $calculator->calculate($firstChild, [$parent, $secondChild], new \DateTimeImmutable('2026-06-01'));
        $outcomeSecond = $calculator->calculate($secondChild, [$parent, $firstChild], new \DateTimeImmutable('2026-06-01'));

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

    /**
     * `resolveFamilyRole()` ist das Gegenstück zur automatischen Umstellung der Beitragsberechnung
     * oben: Ist ein Kind über die Kinder-Altersspanne hinausgewachsen, wechselt nicht nur die
     * Berechnung, sondern auch `familyRole` selbst auf „Einzelperson" (`FamilyRole::None`) — die
     * aufrufende Stelle (siehe `RecalculateAllMemberContributionsUseCase`) speichert das dann.
     */
    public function testResolveFamilyRoleSwitchesAgedOutChildToNone(): void
    {
        $calculator = $this->calculator();
        $formerChild = $this->member(birthDate: '2000-01-01', familyRole: FamilyRole::Child); // 26 Jahre

        self::assertSame(FamilyRole::None, $calculator->resolveFamilyRole($formerChild, [], new \DateTimeImmutable('2026-06-01')));
    }

    public function testResolveFamilyRoleKeepsAChildStillWithinTheChildPricingAge(): void
    {
        $calculator = $this->calculator();
        $child = $this->member(birthDate: '2015-01-01', familyRole: FamilyRole::Child); // 11 Jahre

        self::assertSame(FamilyRole::Child, $calculator->resolveFamilyRole($child, [], new \DateTimeImmutable('2026-06-01')));
    }

    public function testResolveFamilyRoleLeavesNoneUnchanged(): void
    {
        $calculator = $this->calculator();
        $member = $this->member(birthDate: '2005-01-01', familyRole: FamilyRole::None);

        self::assertSame(FamilyRole::None, $calculator->resolveFamilyRole($member, [], new \DateTimeImmutable('2026-06-01')));
    }

    /**
     * Kehrseite von `hasQualifyingChild()`: Gibt es im Haushalt gar kein Kind (mehr), das
     * qualifiziert, ist es laut Beitragsordnung keine „Familie" mehr — dann werden auch
     * Hauptmitglied und Familienangehörige/r automatisch zu Einzelpersonen, nicht nur die
     * Berechnung wechselt (kein Familienrabatt mehr), sondern auch `familyRole` selbst.
     */
    #[DataProvider('headOrPartnerProvider')]
    public function testResolveFamilyRoleSwitchesHeadOrPartnerToNoneWithoutAnyQualifyingChildInTheHousehold(FamilyRole $familyRole): void
    {
        $calculator = $this->calculator();
        $candidate = $this->member(birthDate: '1985-01-01', familyRole: $familyRole, id: 'candidate');
        $otherAdult = $this->member(birthDate: '1987-01-01', familyRole: FamilyRole::Partner, id: 'other-adult');
        $formerChild = $this->member(birthDate: '2000-01-01', familyRole: FamilyRole::Child, id: 'former-child'); // 26 Jahre, herausgewachsen

        self::assertSame(
            FamilyRole::None,
            $calculator->resolveFamilyRole($candidate, [$otherAdult, $formerChild], new \DateTimeImmutable('2026-06-01')),
        );
    }

    #[DataProvider('headOrPartnerProvider')]
    public function testResolveFamilyRoleKeepsHeadOrPartnerWithAQualifyingChildInTheHousehold(FamilyRole $familyRole): void
    {
        $calculator = $this->calculator();
        $candidate = $this->member(birthDate: '1985-01-01', familyRole: $familyRole, id: 'candidate');
        $child = $this->member(birthDate: '2015-01-01', familyRole: FamilyRole::Child, id: 'child'); // 11 Jahre, qualifiziert noch

        self::assertSame(
            $familyRole,
            $calculator->resolveFamilyRole($candidate, [$child], new \DateTimeImmutable('2026-06-01')),
        );
    }

    /**
     * Abgrenzung zu den beiden Tests oben: Eine frisch beigetretene Familie ganz ohne jemals ein
     * Kind (kein einziges `familyRole: Child`-Mitglied im Haushalt) darf nicht automatisch zu
     * Einzelpersonen werden — die Regel greift nur, wenn tatsächlich Kinder da waren und
     * herausgewachsen sind, nicht schon bei bloßem Fehlen von Kindern von Anfang an.
     */
    #[DataProvider('headOrPartnerProvider')]
    public function testResolveFamilyRoleKeepsHeadOrPartnerWhenTheHouseholdNeverHadAChildAtAll(FamilyRole $familyRole): void
    {
        $calculator = $this->calculator();
        $candidate = $this->member(birthDate: '1985-01-01', familyRole: $familyRole, id: 'candidate');
        $otherAdult = $this->member(birthDate: '1987-01-01', familyRole: FamilyRole::Partner, id: 'other-adult');

        self::assertSame(
            $familyRole,
            $calculator->resolveFamilyRole($candidate, [$otherAdult], new \DateTimeImmutable('2026-06-01')),
        );
    }

    /**
     * @return iterable<string, array{FamilyRole}>
     */
    public static function headOrPartnerProvider(): iterable
    {
        yield 'Head' => [FamilyRole::Head];
        yield 'Partner' => [FamilyRole::Partner];
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
        $parent = $this->member(birthDate: '1985-01-01', familyRole: FamilyRole::Head, id: 'parent');
        $first = $this->member(birthDate: '2010-01-01', familyRole: FamilyRole::Child, id: 'first');
        $second = $this->member(birthDate: '2012-01-01', familyRole: FamilyRole::Child, id: 'second');
        $third = $this->member(birthDate: '2014-01-01', familyRole: FamilyRole::Child, id: 'third');

        $outcome = $calculator->calculate($third, [$parent, $first, $second], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::FamilyChildExempt, $outcome->category);
    }

    /**
     * Bei zwei Kindern mit identischem Geburtsdatum (z. B. Zwillingen) muss die Geburtsreihenfolge
     * trotzdem eindeutig sein: genau eines der beiden zahlt (Rang 2), das andere ist als drittes
     * Kind beitragsfrei (Rang 3) — nicht etwa beide beitragsfrei, weil jedes für sich genommen
     * (als jeweils zuletzt an die Liste angehängter Kandidat) den höheren Rang berechnet.
     */
    public function testTwinsWithIdenticalBirthDateGetComplementaryOrdinalsNotBothExempt(): void
    {
        $calculator = $this->calculator();
        $parent = $this->member(birthDate: '1981-08-01', familyRole: FamilyRole::Head, id: 'parent');
        $oldest = $this->member(birthDate: '2013-11-25', familyRole: FamilyRole::Child, id: 'oldest');
        $twinOne = $this->member(birthDate: '2017-02-22', familyRole: FamilyRole::Child, id: 'twin-one');
        $twinTwo = $this->member(birthDate: '2017-02-22', familyRole: FamilyRole::Child, id: 'twin-two');

        $outcomeOldest = $calculator->calculate($oldest, [$parent, $twinOne, $twinTwo], new \DateTimeImmutable('2026-06-01'));
        $outcomeTwinOne = $calculator->calculate($twinOne, [$parent, $oldest, $twinTwo], new \DateTimeImmutable('2026-06-01'));
        $outcomeTwinTwo = $calculator->calculate($twinTwo, [$parent, $oldest, $twinOne], new \DateTimeImmutable('2026-06-01'));

        self::assertSame(ContributionCategory::FamilyChildPaying, $outcomeOldest->category);
        self::assertEqualsCanonicalizing(
            [ContributionCategory::FamilyChildExempt, ContributionCategory::FamilyChildPaying],
            [$outcomeTwinOne->category, $outcomeTwinTwo->category],
            'Genau ein Zwilling zahlt, der andere ist beitragsfrei - nicht beide dasselbe.',
        );
    }

    /**
     * Ein über 21-jähriges Geschwisterkind bleibt zwar `familyRole: Child` (siehe Beitragsordnung),
     * zählt aber nicht mehr zu den „ersten drei Kindern" der Familie — sonst würde ein jüngeres,
     * tatsächlich erst zweites aktives Kind fälschlich als drittes (und damit beitragsfrei) gelten.
     */
    public function testAgedOutSiblingDoesNotCountTowardsTheThirdChildOrdinal(): void
    {
        $calculator = $this->calculator();
        $parent = $this->member(birthDate: '1975-01-01', familyRole: FamilyRole::Head, id: 'parent');
        $agedOutSibling = $this->member(birthDate: '2000-01-01', familyRole: FamilyRole::Child, id: 'aged-out'); // 26 Jahre
        $youngChildOne = $this->member(birthDate: '2015-01-01', familyRole: FamilyRole::Child, id: 'young-one');
        $youngChildTwo = $this->member(birthDate: '2017-01-01', familyRole: FamilyRole::Child, id: 'young-two');

        $outcomeYoungTwo = $calculator->calculate(
            $youngChildTwo,
            [$parent, $agedOutSibling, $youngChildOne],
            new \DateTimeImmutable('2026-06-01'),
        );

        self::assertSame(ContributionCategory::FamilyChildPaying, $outcomeYoungTwo->category);
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
        bool $contributionLiable = true,
        ?string $leftAt = null,
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
            leftAt: $leftAt === null ? null : new \DateTimeImmutable($leftAt),
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
            contributionLiable: $contributionLiable,
        );
    }
}
