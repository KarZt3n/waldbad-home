<?php

namespace App\Tests\Unit\Logic\Membership\WorkAssignmentRefund\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Event\HelpRequest\Query\GetMemberWorkedMinutesQuery;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\DirectDebit\DirectDebitBookingTransactionInterface;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitCreditorManagerInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\MemberAccess\Service\WorkAssignmentCreditCalculator;
use App\Logic\Membership\MemberAccess\Service\WorkAssignmentCreditConfig;
use App\Logic\Membership\PaymentInterval;
use App\Logic\Membership\WorkAssignment\MemberWorkAssignmentProviderInterface;
use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentParticipation;
use App\Logic\Membership\WorkAssignmentRefund\CreditTransferFileWriterInterface;
use App\Logic\Membership\WorkAssignmentRefund\Dto\ExportHouseholdWorkAssignmentRefundRequest;
use App\Logic\Membership\WorkAssignmentRefund\Dto\ExportWorkAssignmentRefundsRequest;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundMemberResponse;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundOverviewEntryResponse;
use App\Logic\Membership\WorkAssignmentRefund\Manager\WorkAssignmentRefundRecordManagerInterface;
use App\Logic\Membership\WorkAssignmentRefund\Model\CreditTransferBatch;
use App\Logic\Membership\WorkAssignmentRefund\Model\CreditTransferTransaction;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;
use App\Logic\Membership\WorkAssignmentRefund\Query\GetWorkAssignmentRefundDetailQuery;
use App\Logic\Membership\WorkAssignmentRefund\Query\GetWorkAssignmentRefundOverviewQuery;
use App\Logic\Membership\WorkAssignmentRefund\Service\WorkAssignmentRefundBookkeeper;
use App\Logic\Membership\WorkAssignmentRefund\Service\WorkAssignmentRefundExporter;
use App\Logic\Membership\WorkAssignmentRefund\Service\WorkAssignmentRefundPlanner;
use App\Logic\Membership\WorkAssignmentRefund\UseCase\ExportHouseholdWorkAssignmentRefundUseCase;
use App\Logic\Membership\WorkAssignmentRefund\UseCase\ExportWorkAssignmentRefundsUseCase;
use PHPUnit\Framework\TestCase;

/**
 * Zuschlag 15 € bei 5 Stunden je Arbeitseinsatz = 3 € je Stunde. Haushalte:
 * - Bad-a: Zahler + Kind (2 Zuschläge = 30 €), der Zahler leistet 10 Std. → 30 € an den Zahler
 * - Bad-b: Platzhalter-IBAN → nicht überweisbar
 * - Bad-c: kein Zuschlag → nichts zu überweisen
 * - Bad-d: 5 Std. = 15 €, davon 10 € schon überwiesen → 5 €
 * - Bad-e: keine Stunden → taucht nicht auf
 */
final class ExportWorkAssignmentRefundsUseCaseTest extends TestCase
{
    /** @var list<WorkAssignmentRefundRecord> */
    private array $savedRecords = [];

    public function testOverviewSplitsHouseholdsAndSubtractsEarlierRefunds(): void
    {
        $overview = (new GetWorkAssignmentRefundOverviewQuery($this->planner(), $this->clock()))->execute(null);

        self::assertSame(2026, $overview->year);
        self::assertSame([2026, 2025, 2024], $overview->selectableYears);
        // 30.10.2026 ist ein Freitag → nächster Bankarbeitstag Montag.
        self::assertSame('2026-11-02', $overview->defaultExecutionDate);
        self::assertSame(['Bad-a' => 3000, 'Bad-d' => 500], $this->amounts($overview->valid));
        self::assertSame('Zahler a', $overview->valid[0]->recipientName);
        self::assertSame(['Bad-a', 'Muster', 'A'], [$overview->valid[0]->memberNumber, $overview->valid[0]->lastName, $overview->valid[0]->firstName]);
        self::assertSame(1000, $overview->valid[1]->refundedCents);
        self::assertSame(['Bad-b' => 600], $this->amounts($overview->invalid));
        self::assertSame([['kind' => 'missing_bank_account', 'label' => 'Ohne Bankverbindung', 'count' => 1]], $overview->obstacleCategories);
        self::assertSame(['Bad-c' => 0], $this->amounts($overview->withoutAmount));
    }

    public function testExportsOneTransferPerHouseholdAndBooksEveryRefund(): void
    {
        $batches = [];
        $writer = $this->createStub(CreditTransferFileWriterInterface::class);
        $writer->method('write')->willReturnCallback(static function (CreditTransferBatch $batch) use (&$batches): string {
            $batches[] = $batch;

            return '<xml/>';
        });

        $file = $this->useCase($writer)->execute(new ExportWorkAssignmentRefundsRequest(2026, new \DateTimeImmutable('2026-11-02'), ['Bad-d', 'Bad-a']));

        self::assertSame('<xml/>', $file->content);
        self::assertSame('sepa-gutschrift-arbeitseinsatz-2026-sammel-20261030-100000.xml', $file->fileName);
        self::assertCount(1, $batches);
        self::assertSame('2026-11-02', $batches[0]->executionDate->format('Y-m-d'));
        self::assertSame(
            [
                ['AE2026-Bad-a-20261030', 3000, 'Zahler a', 'DE89370400440532013000', 'Gutschrift Arbeitseinsatz 2026, Mitgl.-Nr. Bad-a, 10 Std.'],
                ['AE2026-Bad-d-20261030', 500, 'Zahler d', 'DE89370400440532013000', 'Gutschrift Arbeitseinsatz 2026, Mitgl.-Nr. Bad-d, 5 Std.'],
            ],
            array_map(static fn (CreditTransferTransaction $transaction): array => [
                $transaction->endToEndId, $transaction->amountCents, $transaction->creditorName, $transaction->creditorIban, $transaction->remittanceInformation,
            ], $batches[0]->transactions),
        );
        self::assertSame(
            [['Bad-a', 'a', 3000], ['Bad-d', 'd', 500]],
            array_map(static fn (WorkAssignmentRefundRecord $record): array => [$record->primaryMemberNumber, $record->payerMemberId, $record->amountCents], $this->savedRecords),
        );
    }

    public function testRejectsAnOutdatedSelection(): void
    {
        $writer = $this->createMock(CreditTransferFileWriterInterface::class);
        $writer->expects(self::never())->method('write');

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($writer)->execute(new ExportWorkAssignmentRefundsRequest(2026, new \DateTimeImmutable('2026-11-02'), ['Bad-a', 'Bad-b', 'Bad-d']));
    }

    public function testRejectsAnExecutionDateInThePast(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($this->createStub(CreditTransferFileWriterInterface::class))
            ->execute(new ExportWorkAssignmentRefundsRequest(2026, new \DateTimeImmutable('2026-10-29'), ['Bad-a', 'Bad-d']));
    }

    /**
     * Detailansicht: alle Haushaltsmitglieder, Mitglieder mit Zuschlag oder Einsätzen zuerst; nur
     * Einsätze des gewählten Jahres zählen.
     */
    public function testDetailListsHouseholdMembersWithTheirParticipationsOfTheYear(): void
    {
        $workAssignments = $this->createStub(MemberWorkAssignmentProviderInterface::class);
        $workAssignments->method('findForMembers')->willReturn([
            new WorkAssignmentParticipation('a', 'Frühjahrsputz', '2026-04-11', '09:00', 600, []),
            new WorkAssignmentParticipation('a', 'Herbstputz', '2025-10-04', '09:00', 120, []),
        ]);
        $records = $this->createStub(WorkAssignmentRefundRecordManagerInterface::class);
        $records->method('findByYear')->willReturn([]);

        $detail = (new GetWorkAssignmentRefundDetailQuery($this->planner(), $workAssignments, $records, $this->clock()))->execute('Bad-a', 2026);

        self::assertSame('a', $detail->entry->payerId);
        self::assertSame(3000, $detail->entry->amountCents);
        self::assertSame(300, $detail->creditPerHourCents);
        self::assertSame(3000, $detail->totalSurchargeCents);
        self::assertSame(
            [['Bad-a', 1500, 600, ['Frühjahrsputz']], ['Bad-a2', 1500, 0, []]],
            array_map(static fn (WorkAssignmentRefundMemberResponse $member): array => [
                $member->memberNumber,
                $member->surchargeCents,
                $member->workedMinutes,
                array_map(static fn (WorkAssignmentParticipation $participation): string => $participation->eventTitle, $member->participations),
            ], $detail->members),
        );
    }

    /** Einzelexport aus der Detailansicht: nur dieser Haushalt, mit Abzug bereits Überwiesenem. */
    public function testExportsASingleHousehold(): void
    {
        $batches = [];
        $writer = $this->createStub(CreditTransferFileWriterInterface::class);
        $writer->method('write')->willReturnCallback(static function (CreditTransferBatch $batch) use (&$batches): string {
            $batches[] = $batch;

            return '<xml/>';
        });

        $file = (new ExportHouseholdWorkAssignmentRefundUseCase($this->planner(), $this->exporter($writer), $this->clock()))
            ->execute(new ExportHouseholdWorkAssignmentRefundRequest('Bad-a', 2026, new \DateTimeImmutable('2026-11-02')));

        self::assertSame('sepa-gutschrift-arbeitseinsatz-2026-Bad-a-20261030-100000.xml', $file->fileName);
        self::assertSame([3000], array_map(static fn (CreditTransferTransaction $transaction): int => $transaction->amountCents, $batches[0]->transactions));
        self::assertSame([['Bad-a', 3000]], array_map(static fn (WorkAssignmentRefundRecord $record): array => [$record->primaryMemberNumber, $record->amountCents], $this->savedRecords));
    }

    public function testRejectsASingleHouseholdWithObstacles(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('keine echte Bankverbindung');
        (new ExportHouseholdWorkAssignmentRefundUseCase($this->planner(), $this->exporter($this->createStub(CreditTransferFileWriterInterface::class)), $this->clock()))
            ->execute(new ExportHouseholdWorkAssignmentRefundRequest('Bad-b', 2026, new \DateTimeImmutable('2026-11-02')));
    }

    public function testRejectsAYearOutsideTheSelection(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        (new GetWorkAssignmentRefundOverviewQuery($this->planner(), $this->clock()))->execute(2020);
    }

    /**
     * @param list<WorkAssignmentRefundOverviewEntryResponse> $entries
     *
     * @return array<string, int>
     */
    private function amounts(array $entries): array
    {
        return array_column(array_map(static fn (WorkAssignmentRefundOverviewEntryResponse $entry): array => [$entry->primaryMemberNumber, $entry->amountCents], $entries), 1, 0);
    }

    private function useCase(CreditTransferFileWriterInterface $writer): ExportWorkAssignmentRefundsUseCase
    {
        return new ExportWorkAssignmentRefundsUseCase($this->planner(), $this->exporter($writer), $this->clock());
    }

    private function exporter(CreditTransferFileWriterInterface $writer): WorkAssignmentRefundExporter
    {
        $records = $this->createStub(WorkAssignmentRefundRecordManagerInterface::class);
        $records->method('save')->willReturnCallback(function (WorkAssignmentRefundRecord $record): WorkAssignmentRefundRecord {
            $this->savedRecords[] = $record;

            return $record;
        });
        $transaction = $this->createStub(DirectDebitBookingTransactionInterface::class);
        $transaction->method('execute')->willReturnCallback(static function (callable $work): void {
            $work();
        });
        $identifiers = $this->createStub(IdentifierGeneratorInterface::class);
        $identifiers->method('generate')->willReturn('record-1');

        return new WorkAssignmentRefundExporter($writer, new WorkAssignmentRefundBookkeeper($records, $identifiers, $transaction), $this->creditors());
    }

    private function creditors(): DirectDebitCreditorManagerInterface
    {
        $creditors = $this->createStub(DirectDebitCreditorManagerInterface::class);
        $creditors->method('get')->willReturn(new DirectDebitCreditor('Waldbad Borkheide e.V.', 'DE98ZZZ09999999999', 'DE02120300000000202051', null));

        return $creditors;
    }

    private function planner(): WorkAssignmentRefundPlanner
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $all = [
            $this->member('a'),
            $this->member('a2', payerId: 'a'),
            $this->member('b', iban: 'DE36000000000000000000'),
            $this->member('c', surchargeCents: null),
            $this->member('d'),
            $this->member('e'),
        ];
        $members->method('search')->willReturn($all);
        $members->method('findByPrimaryMemberNumber')->willReturnCallback(static fn (string $number): array => array_values(array_filter(
            $all,
            static fn (Member $member): bool => $member->primaryMemberNumber === $number,
        )));
        $rates = $this->createStub(ContributionRateManagerInterface::class);
        $rates->method('findByCategory')->willReturn(new ContributionRate('rate-1', ContributionCategory::WorkAssignmentSurcharge, 'Arbeitseinsatz-Zuschlag', 1500, PaymentInterval::Yearly));
        $minutes = $this->createStub(GetMemberWorkedMinutesQuery::class);
        $minutes->method('minutesByMember')->willReturn(['a' => 600, 'a2' => 0, 'b' => 120, 'c' => 300, 'd' => 300, 'e' => 0]);
        // Nur für die Detailansicht (`plan()`), die im Test ausschließlich Haushalt Bad-a abfragt.
        $minutes->method('totalMinutes')->willReturn(600);
        $calculator = new WorkAssignmentCreditCalculator($rates, $minutes, new WorkAssignmentCreditConfig(
            ['from' => '2026-01-01', 'to' => '2027-01-01'],
            [['valid_from' => '2026-01-01', 'required_hours' => 5]],
        ));
        $records = $this->createStub(WorkAssignmentRefundRecordManagerInterface::class);
        $records->method('findByYear')->willReturn([
            new WorkAssignmentRefundRecord('record-0', 'Bad-d', 'd', 2026, 1000, 'WB-AE-1', new \DateTimeImmutable('2026-09-01')),
        ]);

        return new WorkAssignmentRefundPlanner($members, $calculator, $this->creditors(), $records);
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-30 10:00:00'));

        return $clock;
    }

    private function member(string $id, ?string $payerId = null, ?int $surchargeCents = 1500, string $iban = 'DE89370400440532013000'): Member
    {
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
            mandateReference: null,
            paymentMethod: PaymentMethod::BankTransfer,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: $isPayer ? PayerType::SelfPayer : PayerType::OtherMember,
            payerMemberId: $payerId,
            nextBookingMonth: 3,
            nextBookingYear: 2027,
            contributionCategory: ContributionCategory::IndividualSenior,
            contributionAmountCents: 5000,
            workAssignmentSurchargeCents: $surchargeCents,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
        );
    }
}
