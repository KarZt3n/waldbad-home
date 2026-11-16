<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\Member\UseCase\DeleteHouseholdUseCase;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class DeleteHouseholdUseCaseTest extends TestCase
{
    public function testDeletesEveryHouseholdMemberAtOnce(): void
    {
        $head = $this->member('head-1', 'M-0001');
        $child = $this->member('child-1', 'M-0002');
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('get')->willReturn($child);
        $members->method('findByPrimaryMemberNumber')->willReturnCallback(
            static fn (string $number): array => $number === 'M-0001' ? [$head, $child] : [],
        );
        $members->method('findByPayerMemberId')->willReturnCallback(
            static fn (string $id): array => $id === 'head-1' ? [$child] : [],
        );
        $members->expects(self::once())->method('deleteAll')->with(['head-1', 'child-1']);
        $members->expects(self::never())->method('delete');

        self::assertSame(2, (new DeleteHouseholdUseCase($members))->execute('child-1'));
    }

    public function testRejectsWhenAMemberOutsideTheFamilyPaysThroughIt(): void
    {
        $head = $this->member('head-1', 'M-0001');
        $outsider = $this->member('other-1', 'M-0009', 'M-0009');
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('get')->willReturn($head);
        $members->method('findByPrimaryMemberNumber')->willReturnCallback(
            static fn (string $number): array => $number === 'M-0001' ? [$head] : [],
        );
        $members->method('findByPayerMemberId')->willReturn([$outsider]);
        $members->expects(self::never())->method('deleteAll');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('gehört nicht zur Familie');
        (new DeleteHouseholdUseCase($members))->execute('head-1');
    }

    private function member(string $id, string $memberNumber, string $primaryMemberNumber = 'M-0001'): Member
    {
        return new Member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: $primaryMemberNumber,
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
