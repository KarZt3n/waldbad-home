<?php

namespace App\Tests\Unit\Logic\Membership\DirectDebit\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\DirectDebit\DirectDebitBookingTransactionInterface;
use App\Logic\Membership\DirectDebit\DirectDebitFileWriterInterface;
use App\Logic\Membership\DirectDebit\Dto\ExportPayerDirectDebitRequest;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitCreditorManagerInterface;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitRecordManagerInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;
use App\Logic\Membership\DirectDebit\Model\DirectDebitBatch;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use App\Logic\Membership\DirectDebit\Model\SequenceType;
use App\Logic\Membership\DirectDebit\Service\DirectDebitBookkeeper;
use App\Logic\Membership\DirectDebit\Service\PayerDirectDebitPlanner;
use App\Logic\Membership\DirectDebit\UseCase\ExportPayerDirectDebitUseCase;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class ExportPayerDirectDebitUseCaseTest extends TestCase
{
    /** @var list<DirectDebitRecord> */
    private array $savedRecords = [];
    /** @var list<Member> */
    private array $savedMembers = [];

    public function testRegularDebitIsRecordedAndAdvancesTheNextBooking(): void
    {
        $this->useCase($this->createStub(DirectDebitFileWriterInterface::class))->execute($this->request(['payer:contribution'], '2027-03-01'));

        self::assertCount(1, $this->savedRecords);
        self::assertSame(2027, $this->savedRecords[0]->contributionYear);
        self::assertSame(SequenceType::Recurring, $this->savedRecords[0]->sequenceType);
        self::assertSame(5000, $this->savedRecords[0]->amountCents);
        self::assertCount(1, $this->savedMembers);
        self::assertSame(2028, $this->savedMembers[0]->nextBookingYear);
        self::assertSame(3, $this->savedMembers[0]->nextBookingMonth);
    }

    public function testJoiningYearDebitIsRecordedWithoutAdvancingTheNextBooking(): void
    {
        $this->useCase($this->createStub(DirectDebitFileWriterInterface::class), joinedAt: '2026-09-01')
            ->execute(new ExportPayerDirectDebitRequest('payer', new \DateTimeImmutable('2026-10-01'), SequenceType::First, 'Beitrag 2026', ['payer:contribution']));

        self::assertCount(1, $this->savedRecords);
        self::assertSame(2026, $this->savedRecords[0]->contributionYear);
        self::assertSame(SequenceType::First, $this->savedRecords[0]->sequenceType);
        self::assertSame([], $this->savedMembers);
    }

    public function testWritesOneTransactionOverTheSelectedPositions(): void
    {
        $captured = null;
        $writer = $this->createMock(DirectDebitFileWriterInterface::class);
        $writer->expects(self::once())->method('write')->willReturnCallback(
            static function (DirectDebitBatch $batch) use (&$captured): string {
                $captured = $batch;

                return '<xml/>';
            },
        );

        $file = $this->useCase($writer)->execute($this->request(['payer:contribution']));

        self::assertSame('<xml/>', $file->content);
        self::assertSame('sepa-lastschrift-Bad-01000-20261001.xml', $file->fileName);
        self::assertInstanceOf(DirectDebitBatch::class, $captured);
        self::assertCount(1, $captured->transactions);
        $transaction = $captured->transactions[0];
        self::assertSame(5000, $transaction->amountCents);
        self::assertSame('MANDAT-1', $transaction->mandateReference);
        self::assertSame('Erika Musterfrau', $transaction->debtorName);
        self::assertSame('Bad-01000-20261001', $transaction->endToEndId);
        self::assertSame(SequenceType::Recurring, $captured->sequenceType);
    }

    public function testRejectsUnknownPositions(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Vorschau neu öffnen');
        $this->useCase($this->createStub(DirectDebitFileWriterInterface::class))->execute($this->request(['other:contribution']));
    }

    public function testRejectsEmptySelection(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($this->createStub(DirectDebitFileWriterInterface::class))->execute($this->request([]));
    }

    public function testRejectsCollectionDateInThePast(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Fälligkeitsdatum');
        $this->useCase($this->createStub(DirectDebitFileWriterInterface::class))->execute($this->request(['payer:contribution'], '2026-09-28'));
    }

    public function testBlockersPreventTheExport(): void
    {
        $writer = $this->createMock(DirectDebitFileWriterInterface::class);
        $writer->expects(self::never())->method('write');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Gläubigerdaten');
        $this->useCase($writer, DirectDebitCreditor::empty())->execute($this->request(['payer:contribution']));
    }

    /**
     * @param list<string> $positionIds
     */
    private function request(array $positionIds, string $collectionDate = '2026-10-01'): ExportPayerDirectDebitRequest
    {
        return new ExportPayerDirectDebitRequest('payer', new \DateTimeImmutable($collectionDate), SequenceType::Recurring, 'Mitgliedsbeitrag 2026', $positionIds);
    }

    private function useCase(DirectDebitFileWriterInterface $writer, ?DirectDebitCreditor $creditor = null, string $joinedAt = '2020-01-01'): ExportPayerDirectDebitUseCase
    {
        $payer = new Member(
            id: 'payer',
            memberNumber: 'Bad-01000',
            primaryMemberNumber: 'Bad-01000',
            salutation: Salutation::Ms,
            lastName: 'Musterfrau',
            firstName: 'Erika',
            birthDate: new \DateTimeImmutable('1985-05-05'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable($joinedAt),
            leftAt: null,
            function: MemberFunction::Member,
            accountHolder: 'Erika Musterfrau',
            iban: 'DE89370400440532013000',
            bankName: null,
            mandateReference: 'MANDAT-1',
            paymentMethod: PaymentMethod::SepaDirectDebit,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: PayerType::SelfPayer,
            payerMemberId: null,
            nextBookingMonth: 3,
            nextBookingYear: 2027,
            contributionCategory: ContributionCategory::IndividualSenior,
            contributionAmountCents: 5000,
            workAssignmentSurchargeCents: 1500,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
            mandateValidFrom: new \DateTimeImmutable('2020-03-01'),
        );
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('get')->willReturn($payer);
        $members->method('findByPayerMemberId')->willReturn([]);
        $members->method('save')->willReturnCallback(function (Member $member): Member {
            $this->savedMembers[] = $member;

            return $member;
        });
        $records = $this->createStub(DirectDebitRecordManagerInterface::class);
        $records->method('findByPayerMemberId')->willReturn([]);
        $records->method('save')->willReturnCallback(function (DirectDebitRecord $record): DirectDebitRecord {
            $this->savedRecords[] = $record;

            return $record;
        });
        $transaction = $this->createStub(DirectDebitBookingTransactionInterface::class);
        $transaction->method('execute')->willReturnCallback(static function (callable $work): void {
            $work();
        });
        $identifiers = $this->createStub(IdentifierGeneratorInterface::class);
        $identifiers->method('generate')->willReturn('record-1');
        $creditors = $this->createStub(DirectDebitCreditorManagerInterface::class);
        $creditors->method('get')->willReturn($creditor ?? new DirectDebitCreditor('Waldbad Borkheide e.V.', 'DE98ZZZ09999999999', 'DE02120300000000202051', null));
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-28 10:00:00'));

        return new ExportPayerDirectDebitUseCase(
            new PayerDirectDebitPlanner($members, $this->createStub(ContributionRateManagerInterface::class), $creditors, $records),
            $writer,
            $clock,
            new DirectDebitBookkeeper($records, $members, $identifiers, $transaction),
        );
    }
}
