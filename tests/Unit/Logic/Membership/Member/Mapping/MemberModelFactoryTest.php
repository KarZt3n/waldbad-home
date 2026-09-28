<?php

namespace App\Tests\Unit\Logic\Membership\Member\Mapping;

use App\Logic\Membership\Member\Dto\UpdateMemberRequest;
use App\Logic\Membership\Member\Mapping\MemberModelFactory;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class MemberModelFactoryTest extends TestCase
{
    public function testPaidByOtherMemberClearsBookingAndAccountData(): void
    {
        $member = (new MemberModelFactory())->rebuildFromRequest($this->request(PayerType::OtherMember, 'payer-id', 5, 2027), $this->current());

        self::assertSame(PaymentMethod::NotSpecified, $member->paymentMethod);
        self::assertNull($member->nextBookingMonth);
        self::assertNull($member->nextBookingYear);
        self::assertNull($member->accountHolder);
        self::assertNull($member->iban);
        self::assertNull($member->bankName);
        self::assertNull($member->mandateReference);
        self::assertNull($member->mandateValidFrom);
        self::assertNull($member->mandateValidUntil);
    }

    public function testSelfPayerWithoutBookingGetsMarchOfTheYearAfterJoining(): void
    {
        $member = (new MemberModelFactory())->rebuildFromRequest($this->request(PayerType::SelfPayer, null, null, null), $this->current());

        self::assertSame(PaymentMethod::SepaDirectDebit, $member->paymentMethod);
        self::assertSame(3, $member->nextBookingMonth);
        self::assertSame(2021, $member->nextBookingYear);
        self::assertSame('DE89370400440532013000', $member->iban);
        self::assertSame('2020-03-01', $member->mandateValidFrom?->format('Y-m-d'));
    }

    private function request(PayerType $payerType, ?string $payerMemberId, ?int $month, ?int $year): UpdateMemberRequest
    {
        return new UpdateMemberRequest(
            id: 'member',
            version: 1,
            memberNumber: 'Bad-00001',
            primaryMemberNumber: 'Bad-00001',
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: 'Max',
            birthDate: new \DateTimeImmutable('2000-01-01'),
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
            bankName: 'Testbank',
            mandateReference: 'Bad-00001',
            paymentMethod: PaymentMethod::SepaDirectDebit,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: $payerType,
            payerMemberId: $payerMemberId,
            nextBookingMonth: $month,
            nextBookingYear: $year,
            mandateValidFrom: new \DateTimeImmutable('2020-03-01'),
            mandateValidUntil: new \DateTimeImmutable('2030-12-31'),
        );
    }

    private function current(): Member
    {
        return new Member(
            id: 'member',
            memberNumber: 'Bad-00001',
            primaryMemberNumber: 'Bad-00001',
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: 'Max',
            birthDate: new \DateTimeImmutable('2000-01-01'),
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
            mandateReference: 'Bad-00001',
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
