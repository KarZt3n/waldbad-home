<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Dto\SageGsImportRequest;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Mapping\MemberModelFactory;
use App\Logic\Membership\Member\MemberImportTransactionInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\PaymentInterval;
use App\Logic\Membership\Member\UseCase\ImportSageGsMembersUseCase;
use PHPUnit\Framework\TestCase;

final class ImportSageGsMembersUseCaseTest extends TestCase
{
    public function testDryRunResolvesPayerAppearingLaterWithoutWriting(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturn(null);
        $members->expects(self::never())->method('save');
        $ids = $this->createStub(IdentifierGeneratorInterface::class);
        $ids->method('generate')->willReturnOnConsecutiveCalls('child-id', 'payer-id');
        $transaction = $this->createMock(MemberImportTransactionInterface::class);
        $transaction->expects(self::never())->method('execute');
        $result = (new ImportSageGsMembersUseCase($members, $ids, new MemberModelFactory(), $transaction))
            ->execute(new SageGsImportRequest([1 => $this->row('TEST-2', 'TEST-1'), 2 => $this->row('TEST-1')]));
        self::assertSame([], $result->errors);
        self::assertSame(2, $result->created);
    }

    public function testDuplicateNumbersPreventEveryWrite(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturn(null);
        $members->expects(self::never())->method('save');
        $ids = $this->createStub(IdentifierGeneratorInterface::class);
        $ids->method('generate')->willReturn('test-id');
        $transaction = $this->createMock(MemberImportTransactionInterface::class);
        $transaction->expects(self::never())->method('execute');
        $result = (new ImportSageGsMembersUseCase($members, $ids, new MemberModelFactory(), $transaction))
            ->execute(new SageGsImportRequest([1 => $this->row('TEST-1'), 2 => $this->row('TEST-1')], true));
        self::assertCount(1, $result->errors);
        self::assertSame(2, $result->errors[0]->rowNumber);
    }

    public function testExecuteUsesTransactionAndDoesNotChargeFees(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findByMemberNumber')->willReturn(null);
        $members->expects(self::once())->method('save')->willReturnCallback(static function (\App\Logic\Membership\Member\Model\Member $member): \App\Logic\Membership\Member\Model\Member {
            self::assertSame([], $member->oneTimeCharges);
            self::assertNull($member->contributionAmountCents);
            return $member;
        });
        $ids = $this->createStub(IdentifierGeneratorInterface::class);
        $ids->method('generate')->willReturn('test-id');
        $transaction = $this->createMock(MemberImportTransactionInterface::class);
        $transaction->expects(self::once())->method('execute')->willReturnCallback(static function (callable $work): void { $work(); });
        $result = (new ImportSageGsMembersUseCase($members, $ids, new MemberModelFactory(), $transaction))
            ->execute(new SageGsImportRequest([1 => $this->row('TEST-1')], true));
        self::assertSame([], $result->errors);
    }

    private function row(string $number, ?string $payer = null): CreateMemberRequest
    {
        return new CreateMemberRequest(
            $number, $payer, Salutation::Diverse, 'Test', 'Beispiel', new \DateTimeImmutable('2000-01-01'),
            'Testweg 1', '12345', 'Testort', null, null, $payer === null ? FamilyRole::None : FamilyRole::Child,
            new \DateTimeImmutable('2020-01-01'), null, true, MemberFunction::Member,
            'Test', 'DE89370400440532013000', null, 'TEST-MANDATE', PaymentMethod::SepaDirectDebit,
            PaymentInterval::Yearly, PaymentDay::First, $payer === null ? PayerType::SelfPayer : PayerType::OtherMember,
            null, $payer, 3, 2027,
        );
    }
}
