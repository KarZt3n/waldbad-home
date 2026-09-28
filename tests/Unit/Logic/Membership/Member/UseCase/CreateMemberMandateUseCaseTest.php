<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\Member\UseCase\CreateMemberMandateUseCase;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class CreateMemberMandateUseCaseTest extends TestCase
{
    public function testCreatesMandateFromMemberNumberAndJoinDate(): void
    {
        $manager = $this->createStub(MemberManagerInterface::class);
        $manager->method('get')->willReturn($this->member(null, PaymentMethod::SepaDirectDebit));
        $manager->method('save')->willReturnArgument(0);

        $response = (new CreateMemberMandateUseCase($manager))->execute('member');

        self::assertSame('WV-Bad-03100-00001', $response->mandateReference);
        self::assertSame('2026-08-14', $response->mandateValidFrom?->format('Y-m-d'));
        self::assertNull($response->mandateValidUntil);
    }

    public function testRejectsAnExistingMandate(): void
    {
        $manager = $this->createStub(MemberManagerInterface::class);
        $manager->method('get')->willReturn($this->member('Bad-03100', PaymentMethod::SepaDirectDebit));

        $this->expectException(BusinessRuleViolationException::class);
        (new CreateMemberMandateUseCase($manager))->execute('member');
    }

    public function testRejectsMembersWithoutDirectDebit(): void
    {
        $manager = $this->createStub(MemberManagerInterface::class);
        $manager->method('get')->willReturn($this->member(null, PaymentMethod::BankTransfer));

        $this->expectException(BusinessRuleViolationException::class);
        (new CreateMemberMandateUseCase($manager))->execute('member');
    }

    private function member(?string $mandateReference, PaymentMethod $paymentMethod): Member
    {
        return new Member(
            id: 'member',
            memberNumber: 'Bad-03100',
            primaryMemberNumber: 'Bad-03100',
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
            joinedAt: new \DateTimeImmutable('2026-08-14'),
            leftAt: null,
            function: MemberFunction::Member,
            accountHolder: 'Max Muster',
            iban: 'DE89370400440532013000',
            bankName: null,
            mandateReference: $mandateReference,
            paymentMethod: $paymentMethod,
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
