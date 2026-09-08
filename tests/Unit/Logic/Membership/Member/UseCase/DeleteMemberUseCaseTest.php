<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
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

        (new DeleteMemberUseCase($members))->execute('member-1');
    }

    public function testRejectsDeletionWhenOtherMembersPayThroughThisMember(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('get')->willReturn($this->member());
        $members->method('findByPayerMemberId')->willReturn([$this->member(id: 'dependent-1')]);
        $members->expects(self::never())->method('delete');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('zahlen über dieses Mitglied');
        (new DeleteMemberUseCase($members))->execute('member-1');
    }

    public function testRejectsDeletionWhenOtherMembersUseItAsPrimaryMemberNumber(): void
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
        $this->expectExceptionMessage('Hauptnummer');
        (new DeleteMemberUseCase($members))->execute('member-1');
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
            active: true,
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
