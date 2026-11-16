<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\MemberPayerTransferTransactionInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;
use App\Logic\Membership\Member\Service\PayerTransfer;
use App\Logic\Membership\Member\UseCase\DeleteMemberUseCase;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class DeleteMemberUseCaseTest extends TestCase
{
    public function testDeletesAMemberWithoutDependents(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('get')->willReturn($this->member());
        $members->method('findByPayerMemberId')->willReturn([]);
        $members->method('findByPrimaryMemberNumber')->willReturn([$this->member()]);
        $members->expects(self::once())->method('delete')->with('member-1');
        $payerTransfer = $this->createMock(PayerTransfer::class);
        $payerTransfer->expects(self::never())->method('transfer');

        $this->useCase($members, $payerTransfer)->execute('member-1');
    }

    public function testRejectsDeletionOfAPayerWithoutSuccessor(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('get')->willReturn($this->member());
        $members->method('findByPayerMemberId')->willReturn([$this->member(id: 'dependent-1')]);
        $members->method('findByPrimaryMemberNumber')->willReturn([$this->member()]);
        $members->expects(self::never())->method('delete');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('neuen Zahler');
        $this->useCase($members)->execute('member-1');
    }

    public function testRejectsDeletionOfAHeadWithoutSuccessor(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('get')->willReturn($this->member());
        $members->method('findByPayerMemberId')->willReturn([]);
        $members->method('findByPrimaryMemberNumber')->willReturn([
            $this->member(),
            $this->member(id: 'child-1'),
        ]);
        $members->expects(self::never())->method('delete');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Hauptmitglied');
        $this->useCase($members)->execute('member-1');
    }

    public function testTransfersToTheSuccessorThenDeletesAndRecalculatesInOneTransaction(): void
    {
        $member = $this->member();
        $successor = $this->member(id: 'child-1');
        $calls = [];
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('get')->willReturn($member);
        $members->method('findByPayerMemberId')->willReturn([$successor]);
        $members->method('findByPrimaryMemberNumber')->willReturn([$member, $successor]);
        $members->expects(self::once())->method('delete')->with('member-1')
            ->willReturnCallback(static function () use (&$calls): void { $calls[] = 'delete'; });
        $payerTransfer = $this->createMock(PayerTransfer::class);
        $payerTransfer->expects(self::once())->method('transfer')
            ->with($member, 'child-1', true, true, false)
            ->willReturnCallback(static function () use (&$calls, $successor): Member {
                $calls[] = 'transfer';

                return $successor;
            });
        $recalculator = $this->createMock(HouseholdContributionRecalculator::class);
        $recalculator->expects(self::once())->method('recalculate')->with($successor)
            ->willReturnCallback(static function () use (&$calls, $successor): Member {
                $calls[] = 'recalculate';

                return $successor;
            });
        $transaction = $this->createMock(MemberPayerTransferTransactionInterface::class);
        $transaction->expects(self::once())->method('execute')
            ->willReturnCallback(static function (callable $work) use (&$calls): void {
                $calls[] = 'begin';
                $work();
                $calls[] = 'commit';
            });

        (new DeleteMemberUseCase($members, $payerTransfer, $recalculator, $transaction))->execute('member-1', 'child-1', true);

        self::assertSame(['begin', 'transfer', 'delete', 'recalculate', 'commit'], $calls);
    }

    private function useCase(MemberManagerInterface $members, ?PayerTransfer $payerTransfer = null): DeleteMemberUseCase
    {
        $transaction = $this->createStub(MemberPayerTransferTransactionInterface::class);
        $transaction->method('execute')->willReturnCallback(static function (callable $work): void { $work(); });

        return new DeleteMemberUseCase(
            $members,
            $payerTransfer ?? $this->createStub(PayerTransfer::class),
            $this->createStub(HouseholdContributionRecalculator::class),
            $transaction,
        );
    }

    private function member(string $id = 'member-1'): Member
    {
        return new Member(
            id: $id,
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
