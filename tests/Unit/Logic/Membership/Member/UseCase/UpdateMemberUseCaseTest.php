<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Dto\UpdateMemberRequest;
use App\Logic\Membership\Member\Mapping\MemberModelFactory;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;
use App\Logic\Membership\Member\UseCase\UpdateMemberUseCase;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class UpdateMemberUseCaseTest extends TestCase
{
    public function testSwitchingToBoardDisablesContributionLiabilityAndRecalculatesHousehold(): void
    {
        $current = $this->member(function: MemberFunction::Member, contributionLiable: true);
        // Das Formular könnte theoretisch weiterhin "true" senden — maßgeblich ist der
        // serverseitige Automatismus, nicht der übermittelte Wert.
        $request = $this->request(function: MemberFunction::Board, contributionLiable: true);

        $manager = $this->createMock(MemberManagerInterface::class);
        $manager->method('get')->willReturn($current);
        $manager->expects(self::once())->method('save')->willReturnCallback(static function (Member $member): Member {
            self::assertFalse($member->contributionLiable);
            self::assertSame(MemberFunction::Board, $member->function);

            return $member;
        });

        $recalculator = $this->createMock(HouseholdContributionRecalculator::class);
        $recalculator->expects(self::once())->method('recalculate')->willReturnCallback(
            static fn (Member $member): Member => $member,
        );

        $result = (new UpdateMemberUseCase($manager, new MemberModelFactory(), $recalculator))->execute($request);

        self::assertFalse($result->member->contributionLiable);
    }

    public function testSwitchingAwayFromBoardReenablesContributionLiabilityAndRecalculatesHousehold(): void
    {
        $current = $this->member(function: MemberFunction::Board, contributionLiable: false);
        $request = $this->request(function: MemberFunction::Member, contributionLiable: false);

        $manager = $this->createMock(MemberManagerInterface::class);
        $manager->method('get')->willReturn($current);
        $manager->expects(self::once())->method('save')->willReturnCallback(static function (Member $member): Member {
            self::assertTrue($member->contributionLiable);
            self::assertSame(MemberFunction::Member, $member->function);

            return $member;
        });

        $recalculator = $this->createMock(HouseholdContributionRecalculator::class);
        $recalculator->expects(self::once())->method('recalculate')->willReturnCallback(
            static fn (Member $member): Member => $member,
        );

        $result = (new UpdateMemberUseCase($manager, new MemberModelFactory(), $recalculator))->execute($request);

        self::assertTrue($result->member->contributionLiable);
    }

    public function testUnrelatedFieldChangeDoesNotToggleContributionLiabilityButRecalculatesHousehold(): void
    {
        $current = $this->member(function: MemberFunction::Member, contributionLiable: true);
        $request = $this->request(function: MemberFunction::Member, contributionLiable: true, city: 'Neustadt');

        $manager = $this->createMock(MemberManagerInterface::class);
        $manager->method('get')->willReturn($current);
        $manager->expects(self::once())->method('save')->willReturnCallback(static function (Member $member): Member {
            self::assertTrue($member->contributionLiable);
            self::assertSame('Neustadt', $member->city);

            return $member;
        });

        $recalculator = $this->createMock(HouseholdContributionRecalculator::class);
        $recalculator->expects(self::once())->method('recalculate')->willReturnArgument(0);

        (new UpdateMemberUseCase($manager, new MemberModelFactory(), $recalculator))->execute($request);
    }

    public function testRemainingBoardMemberStaysExemptAndHouseholdIsRecalculated(): void
    {
        $current = $this->member(function: MemberFunction::Board, contributionLiable: false);
        $request = $this->request(function: MemberFunction::Board, contributionLiable: false);

        $manager = $this->createStub(MemberManagerInterface::class);
        $manager->method('get')->willReturn($current);
        $manager->method('save')->willReturnCallback(static fn (Member $member): Member => $member);

        $recalculator = $this->createMock(HouseholdContributionRecalculator::class);
        $recalculator->expects(self::once())->method('recalculate')->willReturnArgument(0);

        $result = (new UpdateMemberUseCase($manager, new MemberModelFactory(), $recalculator))->execute($request);

        self::assertFalse($result->member->contributionLiable);
    }

    /**
     * Die Neuberechnung ist nachgelagert: Scheitert sie, bleibt die Änderung gespeichert und der
     * Grund kommt als Hinweis zurück.
     */
    public function testFailedRecalculationKeepsTheChangeAndReturnsAWarning(): void
    {
        $manager = $this->createMock(MemberManagerInterface::class);
        $manager->method('get')->willReturn($this->member(function: MemberFunction::Member, contributionLiable: true));
        $manager->expects(self::once())->method('save')->willReturnArgument(0);
        $recalculator = $this->createStub(HouseholdContributionRecalculator::class);
        $recalculator->method('recalculate')->willThrowException(new BusinessRuleViolationException('Kein Beitragssatz für Familie: Kind.'));

        $result = (new UpdateMemberUseCase($manager, new MemberModelFactory(), $recalculator))
            ->execute($this->request(function: MemberFunction::Member, contributionLiable: true, city: 'Neustadt'));

        self::assertSame('Neustadt', $result->member->city);
        self::assertStringContainsString('Kein Beitragssatz für Familie: Kind.', (string) $result->contributionWarning);
    }

    public function testChangingTheFamilyAlsoRecalculatesThePreviousFamily(): void
    {
        $remaining = $this->member(function: MemberFunction::Member, contributionLiable: true, id: 'sibling-1');
        $manager = $this->createStub(MemberManagerInterface::class);
        $manager->method('get')->willReturn($this->member(function: MemberFunction::Member, contributionLiable: true));
        $manager->method('save')->willReturnArgument(0);
        $manager->method('findByPrimaryMemberNumber')->willReturn([$remaining]);
        $recalculated = [];
        $recalculator = $this->createStub(HouseholdContributionRecalculator::class);
        $recalculator->method('recalculate')->willReturnCallback(static function (Member $member) use (&$recalculated): Member {
            $recalculated[] = $member->id;

            return $member;
        });

        (new UpdateMemberUseCase($manager, new MemberModelFactory(), $recalculator))
            ->execute($this->request(function: MemberFunction::Member, contributionLiable: true, primaryMemberNumber: 'M-0002'));

        self::assertSame(['member-1', 'sibling-1'], $recalculated);
    }

    private function member(
        MemberFunction $function,
        bool $contributionLiable,
        string $id = 'member-1',
    ): Member {
        return new Member(
            id: $id,
            memberNumber: 'M-0001',
            primaryMemberNumber: 'M-0001',
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: 'Max',
            birthDate: new \DateTimeImmutable('1980-01-01'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            function: $function,
            accountHolder: 'Max Muster',
            iban: 'DE89370400440532013000',
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

    private function request(
        MemberFunction $function,
        bool $contributionLiable,
        string $city = 'Borkheide',
        string $primaryMemberNumber = 'M-0001',
    ): UpdateMemberRequest {
        return new UpdateMemberRequest(
            id: 'member-1',
            version: 1,
            memberNumber: 'M-0001',
            primaryMemberNumber: $primaryMemberNumber,
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: 'Max',
            birthDate: new \DateTimeImmutable('1980-01-01'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: $city,
            email: null,
            phone: null,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            function: $function,
            accountHolder: 'Max Muster',
            iban: 'DE89370400440532013000',
            bankName: null,
            mandateReference: 'M-0001',
            paymentMethod: PaymentMethod::SepaDirectDebit,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: PayerType::SelfPayer,
            payerMemberId: null,
            nextBookingMonth: 3,
            nextBookingYear: 2027,
            contributionLiable: $contributionLiable,
        );
    }
}
