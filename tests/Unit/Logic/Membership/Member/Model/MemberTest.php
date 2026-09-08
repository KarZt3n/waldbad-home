<?php

namespace App\Tests\Unit\Logic\Membership\Member\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class MemberTest extends TestCase
{
    public function testRejectsInvalidIban(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('IBAN');
        $this->member(iban: 'DE00000000000000000000');
    }

    public function testSepaSelfPayerRequiresAccountHolder(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Kontoinhaber');
        $this->member(paymentMethod: PaymentMethod::SepaDirectDebit, accountHolder: null);
    }

    public function testBankTransferSelfPayerDoesNotRequireBankDetails(): void
    {
        $member = $this->member(paymentMethod: PaymentMethod::BankTransfer, accountHolder: null, iban: null, mandateReference: null);

        self::assertNull($member->accountHolder);
        self::assertNull($member->iban);
        self::assertNull($member->mandateReference);
    }

    public function testCashSelfPayerDoesNotRequireBankDetails(): void
    {
        $member = $this->member(paymentMethod: PaymentMethod::Cash, accountHolder: null, iban: null, mandateReference: null);

        self::assertNull($member->accountHolder);
        self::assertNull($member->iban);
        self::assertNull($member->mandateReference);
    }

    public function testNotSpecifiedSelfPayerDoesNotRequireBankDetails(): void
    {
        $member = $this->member(paymentMethod: PaymentMethod::NotSpecified, accountHolder: null, iban: null, mandateReference: null);

        self::assertNull($member->accountHolder);
        self::assertNull($member->iban);
        self::assertNull($member->mandateReference);
    }

    public function testBankTransferSelfPayerStillValidatesIbanFormatWhenGiven(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('IBAN');
        $this->member(paymentMethod: PaymentMethod::BankTransfer, iban: 'DE00000000000000000000');
    }

    public function testRejectsOtherMemberPayerWithoutPayerId(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->member(payerType: PayerType::OtherMember, payerMemberId: null);
    }

    public function testRejectsSelfPayerWithPayerId(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->member(payerType: PayerType::SelfPayer, payerMemberId: 'other-member');
    }

    public function testRejectsLeftAtBeforeJoinedAt(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->member(joinedAt: new \DateTimeImmutable('2026-01-01'), leftAt: new \DateTimeImmutable('2025-01-01'));
    }

    public function testWithContributionReplacesCategoryAndAmount(): void
    {
        $member = $this->member();

        $updated = $member->withContribution(null, null, null);

        self::assertNull($updated->contributionCategory);
        self::assertNull($updated->contributionAmountCents);
        self::assertSame($member->id, $updated->id);
    }

    public function testExemptMemberDoesNotRequireBankDetails(): void
    {
        $member = $this->member(accountHolder: null, iban: null, mandateReference: null, contributionLiable: false);

        self::assertFalse($member->contributionLiable);
        self::assertNull($member->accountHolder);
        self::assertNull($member->iban);
        self::assertNull($member->mandateReference);
    }

    public function testHasLeftIsTrueOnAndAfterTheLeftAtDate(): void
    {
        $member = $this->member(leftAt: new \DateTimeImmutable('2026-05-01'));

        self::assertTrue($member->hasLeft(new \DateTimeImmutable('2026-05-01')));
        self::assertTrue($member->hasLeft(new \DateTimeImmutable('2026-06-01')));
        self::assertFalse($member->hasLeft(new \DateTimeImmutable('2026-04-30')));
    }

    public function testHasLeftIsFalseWithoutLeftAt(): void
    {
        $member = $this->member();

        self::assertFalse($member->hasLeft(new \DateTimeImmutable('2099-01-01')));
    }

    public function testRejectsMandateValidUntilBeforeMandateValidFrom(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Mandat');
        $this->member(
            mandateValidFrom: new \DateTimeImmutable('2026-05-01'),
            mandateValidUntil: new \DateTimeImmutable('2026-04-30'),
        );
    }

    public function testWithMandateReplacesMandateFieldsOnly(): void
    {
        $member = $this->member();

        $updated = $member->withMandate('WV-NEW-REF', new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2030-01-01'));

        self::assertSame('WV-NEW-REF', $updated->mandateReference);
        self::assertEquals(new \DateTimeImmutable('2020-01-01'), $updated->mandateValidFrom);
        self::assertEquals(new \DateTimeImmutable('2030-01-01'), $updated->mandateValidUntil);
        self::assertSame($member->id, $updated->id);
        self::assertSame($member->iban, $updated->iban);
    }

    private function member(
        ?string $iban = 'DE89370400440532013000',
        PayerType $payerType = PayerType::SelfPayer,
        ?string $payerMemberId = null,
        \DateTimeImmutable $joinedAt = new \DateTimeImmutable('2026-01-01'),
        ?\DateTimeImmutable $leftAt = null,
        PaymentMethod $paymentMethod = PaymentMethod::SepaDirectDebit,
        ?string $accountHolder = 'Max Muster',
        ?string $mandateReference = 'M-0001',
        bool $contributionLiable = true,
        ?\DateTimeImmutable $mandateValidFrom = null,
        ?\DateTimeImmutable $mandateValidUntil = null,
    ): Member {
        return new Member(
            id: 'member-1',
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
            joinedAt: $joinedAt,
            leftAt: $leftAt,
            active: true,
            function: MemberFunction::Member,
            accountHolder: $accountHolder,
            iban: $iban,
            bankName: null,
            mandateReference: $mandateReference,
            paymentMethod: $paymentMethod,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: $payerType,
            payerMemberId: $payerMemberId,
            nextBookingMonth: 3,
            nextBookingYear: 2027,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 1,
            contributionLiable: $contributionLiable,
            mandateValidFrom: $mandateValidFrom,
            mandateValidUntil: $mandateValidUntil,
        );
    }
}
