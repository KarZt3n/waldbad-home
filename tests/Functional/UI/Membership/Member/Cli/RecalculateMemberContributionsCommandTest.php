<?php

namespace App\Tests\Functional\UI\Membership\Member\Cli;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Dto\ContributionOutcome;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\Member\Service\BoardFamilyExemptionResolver;
use App\Logic\Membership\Member\Service\MemberContributionCalculator;
use App\Logic\Membership\Member\UseCase\RecalculateAllMemberContributionsUseCase;
use App\Logic\Membership\PaymentInterval;
use App\UI\Membership\Member\Cli\RecalculateMemberContributionsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RecalculateMemberContributionsCommandTest extends TestCase
{
    public function testRecalculatesTheCurrentYearByDefault(): void
    {
        $calculator = $this->createMock(MemberContributionCalculator::class);
        $calculator->method('resolveHouseholdRoles')->willReturnArgument(0);
        $calculator->expects(self::once())->method('calculate')
            ->with(self::anything(), self::anything(), new \DateTimeImmutable('2027-01-01 02:00:00'))
            ->willReturn(new ContributionOutcome(ContributionCategory::IndividualSenior, 5000, null));
        $tester = $this->tester($calculator);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('1 Mitglied(er) neu berechnet.', $tester->getDisplay());
    }

    public function testFailsAndListsMembersThatCouldNotBeCalculated(): void
    {
        $calculator = $this->createStub(MemberContributionCalculator::class);
        $calculator->method('resolveHouseholdRoles')->willReturnArgument(0);
        $calculator->method('calculate')->willThrowException(new BusinessRuleViolationException('Kein Beitragssatz hinterlegt.'));
        $tester = $this->tester($calculator);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('M-0001: Kein Beitragssatz hinterlegt.', $tester->getDisplay());
    }

    public function testRejectsYearsOutsideCurrentAndNextAndInvalidInput(): void
    {
        $tester = $this->tester($this->createStub(MemberContributionCalculator::class));

        self::assertSame(Command::FAILURE, $tester->execute(['--year' => '2030']));
        self::assertStringContainsString('2027 oder 2028', $tester->getDisplay());
        self::assertSame(Command::INVALID, $tester->execute(['--year' => 'nächstes']));
    }

    private function tester(MemberContributionCalculator $calculator): CommandTester
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([$this->member()]);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2027-01-01 02:00:00'));

        return new CommandTester(new RecalculateMemberContributionsCommand(
            new RecalculateAllMemberContributionsUseCase($members, $calculator, $clock, new BoardFamilyExemptionResolver()),
        ));
    }

    private function member(): Member
    {
        return new Member(
            id: 'member-a',
            memberNumber: 'M-0001',
            primaryMemberNumber: 'M-0001',
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
            function: MemberFunction::Member,
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
        );
    }
}
