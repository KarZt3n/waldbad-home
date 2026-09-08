<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Membership\Member\Dto\MandateBackfillRow;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\MemberImportTransactionInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\Member\UseCase\BackfillSageGsMandateUseCase;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class BackfillSageGsMandateUseCaseTest extends TestCase
{
    public function testUnknownMemberNumberIsCountedButNotSaved(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturn(null);
        $members->expects(self::never())->method('save');
        $transaction = $this->createMock(MemberImportTransactionInterface::class);
        $transaction->expects(self::never())->method('execute');

        $result = (new BackfillSageGsMandateUseCase($members, $transaction))
            ->execute([new MandateBackfillRow('M-9999', 'REF', null, null)], true);

        self::assertSame(0, $result->updated);
        self::assertSame(0, $result->unchanged);
        self::assertSame(1, $result->notFound);
    }

    public function testMatchingValuesAreCountedAsUnchanged(): void
    {
        $existing = $this->member(mandateReference: 'REF-1');
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturn($existing);
        $members->expects(self::never())->method('save');
        $transaction = $this->createStub(MemberImportTransactionInterface::class);

        $result = (new BackfillSageGsMandateUseCase($members, $transaction))
            ->execute([new MandateBackfillRow('M-0001', 'REF-1', null, null)], true);

        self::assertSame(0, $result->updated);
        self::assertSame(1, $result->unchanged);
        self::assertSame(0, $result->notFound);
    }

    public function testEmptySourceValuesDoNotOverwriteExistingData(): void
    {
        $existing = $this->member(mandateReference: 'KEPT-REF', mandateValidFrom: new \DateTimeImmutable('2020-01-01'));
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturn($existing);
        $members->expects(self::never())->method('save');
        $transaction = $this->createStub(MemberImportTransactionInterface::class);

        $result = (new BackfillSageGsMandateUseCase($members, $transaction))
            ->execute([new MandateBackfillRow('M-0001', null, null, null)], true);

        self::assertSame(0, $result->updated);
        self::assertSame(1, $result->unchanged);
    }

    public function testDryRunDoesNotWrite(): void
    {
        $existing = $this->member(mandateReference: 'OLD-REF');
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturn($existing);
        $members->expects(self::never())->method('save');
        $transaction = $this->createMock(MemberImportTransactionInterface::class);
        $transaction->expects(self::never())->method('execute');

        $result = (new BackfillSageGsMandateUseCase($members, $transaction))
            ->execute([new MandateBackfillRow('M-0001', 'NEW-REF', null, null)], false);

        self::assertSame(1, $result->updated);
    }

    public function testExecuteWritesChangedMandateFieldsViaTransaction(): void
    {
        $existing = $this->member(mandateReference: 'OLD-REF');
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturn($existing);
        $members->expects(self::once())->method('save')->willReturnCallback(static function (Member $member): Member {
            self::assertSame('NEW-REF', $member->mandateReference);
            self::assertEquals(new \DateTimeImmutable('2024-01-01'), $member->mandateValidFrom);
            self::assertEquals(new \DateTimeImmutable('2029-01-01'), $member->mandateValidUntil);

            return $member;
        });
        $transaction = $this->createMock(MemberImportTransactionInterface::class);
        $transaction->expects(self::once())->method('execute')->willReturnCallback(static function (callable $work): void { $work(); });

        $result = (new BackfillSageGsMandateUseCase($members, $transaction))->execute([
            new MandateBackfillRow('M-0001', 'NEW-REF', new \DateTimeImmutable('2024-01-01'), new \DateTimeImmutable('2029-01-01')),
        ], true);

        self::assertSame(1, $result->updated);
        self::assertSame(0, $result->unchanged);
        self::assertSame(0, $result->notFound);
    }

    private function member(
        ?string $mandateReference,
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
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            active: true,
            function: MemberFunction::Member,
            accountHolder: 'Max Muster',
            iban: 'DE89370400440532013000',
            bankName: null,
            mandateReference: $mandateReference,
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
            mandateValidFrom: $mandateValidFrom,
            mandateValidUntil: $mandateValidUntil,
        );
    }
}
