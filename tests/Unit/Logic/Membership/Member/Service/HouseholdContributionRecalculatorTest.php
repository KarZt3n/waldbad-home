<?php

namespace App\Tests\Unit\Logic\Membership\Member\Service;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Dto\ContributionOutcome;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\Member\Service\BoardFamilyExemptionResolver;
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;
use App\Logic\Membership\Member\Service\MemberContributionCalculator;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class HouseholdContributionRecalculatorTest extends TestCase
{
    public function testRecalculatesAndSavesEveryHouseholdMemberAndReturnsTheRequestedOne(): void
    {
        $head = $this->member('head', 'M-0001');
        $partner = $this->member('partner', 'M-0001');

        $manager = $this->createMock(MemberManagerInterface::class);
        $manager->method('findByPrimaryMemberNumber')->willReturn([$head, $partner]);
        $saved = [];
        $manager->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (Member $member) use (&$saved): Member {
                $saved[] = $member->id;

                return $member->withContribution(ContributionCategory::IndividualSenior, 5000, null);
            },
        );

        $calculator = $this->createStub(MemberContributionCalculator::class);
        $calculator->method('calculate')->willReturn(new ContributionOutcome(ContributionCategory::IndividualSenior, 5000, null));

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        $result = (new HouseholdContributionRecalculator($manager, $calculator, $clock, new BoardFamilyExemptionResolver()))->recalculate($partner);

        self::assertSame(['head', 'partner'], $saved);
        self::assertSame('partner', $result->id);
        self::assertSame(5000, $result->contributionAmountCents);
    }

    public function testBoardMemberInHouseholdExemptsTheWholeFamily(): void
    {
        // Der Partner ist noch als beitragspflichtig gespeichert; da der Kopf Vorstand ist, muss
        // die Neuberechnung ihn (und sich selbst) trotzdem beitragsfrei stellen und speichern.
        $board = $this->member('head', 'M-0001', MemberFunction::Board);
        $partner = $this->member('partner', 'M-0001');

        $manager = $this->createMock(MemberManagerInterface::class);
        $manager->method('findByPrimaryMemberNumber')->willReturn([$board, $partner]);
        $saved = [];
        $manager->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (Member $member) use (&$saved): Member {
                $saved[] = $member;

                return $member;
            },
        );

        $calculator = $this->createMock(MemberContributionCalculator::class);
        $calculator->expects(self::exactly(2))->method('calculate')->willReturnCallback(
            static function (Member $candidate) {
                self::assertFalse($candidate->contributionLiable);

                return new ContributionOutcome(null, 0, null);
            },
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        $result = (new HouseholdContributionRecalculator($manager, $calculator, $clock, new BoardFamilyExemptionResolver()))->recalculate($partner);

        self::assertFalse($result->contributionLiable);
        foreach ($saved as $member) {
            self::assertFalse($member->contributionLiable);
        }
    }

    public function testLeavingBoardReactivatesTheWholeHousehold(): void
    {
        // Der Kopf ist gerade nicht mehr Vorstand (und wurde vom Aufrufer, siehe
        // UpdateMemberUseCase, bereits selbst auf beitragspflichtig gesetzt), der Partner war aber
        // wegen des ehemaligen Vorstands noch als beitragsfrei gespeichert. Ohne verbleibendes
        // Vorstandsmitglied im Haushalt muss die Neuberechnung auch den Partner reaktivieren.
        $formerBoard = $this->member('head', 'M-0001', MemberFunction::Member, contributionLiable: true);
        $stillExemptPartner = $this->member('partner', 'M-0001', MemberFunction::Member, contributionLiable: false);

        $manager = $this->createMock(MemberManagerInterface::class);
        $manager->method('findByPrimaryMemberNumber')->willReturn([$formerBoard, $stillExemptPartner]);
        $saved = [];
        $manager->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (Member $member) use (&$saved): Member {
                $saved[$member->id] = $member;

                return $member;
            },
        );

        $calculator = $this->createMock(MemberContributionCalculator::class);
        $calculator->expects(self::exactly(2))->method('calculate')->willReturnCallback(
            static function (Member $candidate) {
                self::assertTrue($candidate->contributionLiable);

                return new ContributionOutcome(ContributionCategory::IndividualSenior, 5000, null);
            },
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        (new HouseholdContributionRecalculator($manager, $calculator, $clock, new BoardFamilyExemptionResolver()))->recalculate($formerBoard);

        self::assertTrue($saved['partner']->contributionLiable);
    }

    public function testSiblingThatCannotBeReactivatedNamesItselfInTheErrorMessage(): void
    {
        // Der Kopf ist nicht mehr Vorstand und wird gerade selbst neu berechnet; sein Partner war
        // aber wegen des ehemaligen Vorstands beitragsfrei und hat als Selbstzahler mit
        // SEPA-Lastschrift keine hinterlegte IBAN. Die Fehlermeldung muss erkennen lassen, dass sie
        // den Partner betrifft — nicht den angefragten Kopf.
        $formerBoard = $this->member('head', 'M-0001', MemberFunction::Member, contributionLiable: true);
        $cannotReactivate = $this->member('partner', 'M-0002', MemberFunction::Member, contributionLiable: false, iban: null, primaryMemberNumber: 'M-0001');

        $manager = $this->createStub(MemberManagerInterface::class);
        $manager->method('findByPrimaryMemberNumber')->willReturn([$formerBoard, $cannotReactivate]);
        // $formerBoard wird vor $cannotReactivate verarbeitet und erfolgreich gespeichert.
        $manager->method('save')->willReturnCallback(static fn (Member $member): Member => $member);

        $calculator = $this->createStub(MemberContributionCalculator::class);
        $calculator->method('calculate')->willReturn(new ContributionOutcome(ContributionCategory::IndividualSenior, 5000, null));

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessageMatches('/Max Muster \(M-0002\).*IBAN/s');

        (new HouseholdContributionRecalculator($manager, $calculator, $clock, new BoardFamilyExemptionResolver()))->recalculate($formerBoard);
    }

    private function member(
        string $id,
        string $memberNumber,
        MemberFunction $function = MemberFunction::Member,
        bool $contributionLiable = true,
        ?string $iban = 'DE89370400440532013000',
        ?string $primaryMemberNumber = null,
    ): Member {
        return new Member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: $primaryMemberNumber ?? $memberNumber,
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
            leftAt: null,
            active: true,
            function: $function,
            accountHolder: 'Max Muster',
            iban: $iban,
            bankName: null,
            mandateReference: $memberNumber,
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
