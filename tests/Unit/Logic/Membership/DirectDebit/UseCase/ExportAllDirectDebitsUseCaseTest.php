<?php

namespace App\Tests\Unit\Logic\Membership\DirectDebit\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\DirectDebit\DirectDebitBookingTransactionInterface;
use App\Logic\Membership\DirectDebit\DirectDebitFileWriterInterface;
use App\Logic\Membership\DirectDebit\Dto\DirectDebitOverviewEntryResponse;
use App\Logic\Membership\DirectDebit\Dto\ExportAllDirectDebitsRequest;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitCreditorManagerInterface;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitRecordManagerInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitBatch;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;
use App\Logic\Membership\DirectDebit\Model\DirectDebitTransaction;
use App\Logic\Membership\DirectDebit\Model\SequenceType;
use App\Logic\Membership\DirectDebit\Query\GetDirectDebitOverviewQuery;
use App\Logic\Membership\DirectDebit\Service\DirectDebitBookkeeper;
use App\Logic\Membership\DirectDebit\Service\PayerDirectDebitPlanner;
use App\Logic\Membership\DirectDebit\UseCase\ExportAllDirectDebitsUseCase;
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

final class ExportAllDirectDebitsUseCaseTest extends TestCase
{
    /** @var list<Member> */
    private array $savedMembers = [];

    /** @var list<DirectDebitRecord> */
    private array $savedRecords = [];

    /**
     * Fälligkeit 01. und 15. landen in getrennten Zahlungsblöcken; blockierte Zahler (hier
     * Platzhalter-IBAN) und Zahler ohne Betrag fehlen. Jeder Zahler wird mit seinem eigenen Betrag
     * in der Historie festgehalten und seine „Nächste Buchung“ rückt vor.
     */
    public function testWritesOneBatchPerCollectionDateAndBooksEveryPayer(): void
    {
        $batches = [];
        $writer = $this->createStub(DirectDebitFileWriterInterface::class);
        $writer->method('write')->willReturnCallback(static function (DirectDebitBatch ...$written) use (&$batches): string {
            $batches = $written;

            return '<xml/>';
        });

        $file = $this->useCase($writer, $this->members())->execute(new ExportAllDirectDebitsRequest(['first', 'fifteenth', 'family']));

        self::assertSame('<xml/>', $file->content);
        self::assertSame(
            [
                ['2027-03-01', ['Bad-first-20270301', 'Bad-family-20270301'], [6500, 8500]],
                ['2027-03-15', ['Bad-fifteenth-20270315'], [6500]],
            ],
            array_map(static fn (DirectDebitBatch $batch): array => [
                $batch->collectionDate->format('Y-m-d'),
                array_map(static fn (DirectDebitTransaction $transaction): string => $transaction->endToEndId, $batch->transactions),
                array_map(static fn (DirectDebitTransaction $transaction): int => $transaction->amountCents, $batch->transactions),
            ], $batches),
        );
        self::assertSame(
            ['first' => 6500, 'family' => 8500, 'fifteenth' => 6500],
            array_column(array_map(static fn (DirectDebitRecord $record): array => [$record->payerMemberId, $record->amountCents], $this->savedRecords), 1, 0),
        );
        self::assertSame([2028, 2028, 2028], array_map(static fn (Member $member): ?int => $member->nextBookingYear, $this->savedMembers));
    }

    public function testRejectsAnOutdatedSelection(): void
    {
        $writer = $this->createMock(DirectDebitFileWriterInterface::class);
        $writer->expects(self::never())->method('write');

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($writer, $this->members())->execute(new ExportAllDirectDebitsRequest(['first', 'fifteenth', 'family', 'placeholder']));
    }

    public function testOverviewSplitsInvalidAndValidPayersAndCountsPayersWithoutAmount(): void
    {
        $overview = (new GetDirectDebitOverviewQuery($this->planner($this->members()), $this->clock()))->execute();

        self::assertSame(['placeholder'], array_map(static fn (DirectDebitOverviewEntryResponse $entry): string => $entry->payerId, $overview->invalid));
        self::assertSame([['kind' => 'missing_bank_account', 'message' => 'Für den Zahler ist keine echte Bankverbindung hinterlegt.']], $overview->invalid[0]->obstacles);
        self::assertSame([['kind' => 'missing_bank_account', 'label' => 'Ohne Bankverbindung', 'count' => 1]], $overview->obstacleCategories);
        self::assertSame(['first', 'fifteenth', 'family'], array_map(static fn (DirectDebitOverviewEntryResponse $entry): string => $entry->payerId, $overview->valid));
        self::assertSame(8500, $overview->valid[2]->amountCents);
        self::assertSame(['exempt'], array_map(static fn (DirectDebitOverviewEntryResponse $entry): string => $entry->payerId, $overview->withoutAmount));
    }

    /**
     * @return list<Member>
     */
    private function members(): array
    {
        return [
            $this->member('first'),
            $this->member('fifteenth', paymentDay: PaymentDay::Fifteenth),
            $this->member('family'),
            $this->member('child', payerId: 'family', category: ContributionCategory::FamilyChildPaying, amountCents: 2000, surchargeCents: null),
            $this->member('placeholder', iban: 'DE36000000000000000000'),
            $this->member('exempt', liable: false),
        ];
    }

    /**
     * @param list<Member> $members
     */
    private function useCase(DirectDebitFileWriterInterface $writer, array $members): ExportAllDirectDebitsUseCase
    {
        $memberManager = $this->createStub(MemberManagerInterface::class);
        $memberManager->method('save')->willReturnCallback(function (Member $member): Member {
            $this->savedMembers[] = $member;

            return $member;
        });
        $records = $this->createStub(DirectDebitRecordManagerInterface::class);
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

        return new ExportAllDirectDebitsUseCase(
            $this->planner($members),
            $writer,
            $this->clock(),
            new DirectDebitBookkeeper($records, $memberManager, $identifiers, $transaction),
        );
    }

    /**
     * @param list<Member> $members
     */
    private function planner(array $members): PayerDirectDebitPlanner
    {
        $memberManager = $this->createStub(MemberManagerInterface::class);
        $memberManager->method('search')->willReturn($members);
        $records = $this->createStub(DirectDebitRecordManagerInterface::class);
        $records->method('findAll')->willReturn([]);
        $creditors = $this->createStub(DirectDebitCreditorManagerInterface::class);
        $creditors->method('get')->willReturn(new DirectDebitCreditor('Waldbad Borkheide e.V.', 'DE98ZZZ09999999999', 'DE02120300000000202051', null));
        $rates = $this->createStub(ContributionRateManagerInterface::class);
        $rates->method('list')->willReturn([]);

        return new PayerDirectDebitPlanner($memberManager, $rates, $creditors, $records);
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-28 10:00:00'));

        return $clock;
    }

    private function member(
        string $id,
        ?string $payerId = null,
        ContributionCategory $category = ContributionCategory::IndividualSenior,
        int $amountCents = 5000,
        ?int $surchargeCents = 1500,
        PaymentDay $paymentDay = PaymentDay::First,
        string $iban = 'DE89370400440532013000',
        bool $liable = true,
    ): Member {
        $isPayer = $payerId === null;

        return new Member(
            id: $id,
            memberNumber: 'Bad-'.$id,
            primaryMemberNumber: 'Bad-'.($payerId ?? $id),
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: ucfirst($id),
            birthDate: new \DateTimeImmutable('1985-05-05'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            function: MemberFunction::Member,
            accountHolder: $isPayer ? 'Zahler '.$id : null,
            iban: $isPayer ? $iban : null,
            bankName: null,
            mandateReference: $isPayer ? 'MANDAT-'.$id : null,
            paymentMethod: PaymentMethod::SepaDirectDebit,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: $paymentDay,
            payerType: $isPayer ? PayerType::SelfPayer : PayerType::OtherMember,
            payerMemberId: $payerId,
            nextBookingMonth: 3,
            nextBookingYear: 2027,
            contributionCategory: $category,
            contributionAmountCents: $amountCents,
            workAssignmentSurchargeCents: $surchargeCents,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
            contributionLiable: $liable,
            mandateValidFrom: new \DateTimeImmutable('2020-03-01'),
        );
    }
}
