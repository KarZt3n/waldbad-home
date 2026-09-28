<?php

namespace App\Tests\Unit\Logic\Membership\DirectDebit\Service;

use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitCreditorManagerInterface;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitRecordManagerInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;
use App\Logic\Membership\DirectDebit\Model\SequenceType;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use App\Logic\Membership\DirectDebit\Model\DirectDebitPosition;
use App\Logic\Membership\DirectDebit\Service\PayerDirectDebitPlanner;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\ContributionCharge;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class PayerDirectDebitPlannerTest extends TestCase
{
    private const string NOW = '2026-09-28 10:00:00';

    public function testCollectsPositionsOfPayerAndEveryoneThePayerPaysFor(): void
    {
        $payer = $this->member('payer', mandateValidFrom: '2020-03-01', charges: [
            new ContributionCharge('charge-new', 'Beitrittsgebühr Familie', 2000, new \DateTimeImmutable('2026-08-14')),
            new ContributionCharge('charge-old', 'Altgebühr', 500, new \DateTimeImmutable('2024-01-10')),
        ]);
        $child = $this->member('child', payerId: 'payer', category: ContributionCategory::FamilyChildPaying, amountCents: 3000, surchargeCents: null);
        $left = $this->member('left', payerId: 'payer', leftAt: '2026-06-30');
        $board = $this->member('board', payerId: 'payer', liable: false);

        $draft = $this->planner([$payer, $child, $left, $board])->plan('child', new \DateTimeImmutable(self::NOW));

        self::assertSame('payer', $draft->payer->id);
        self::assertSame([], $draft->blockers);
        self::assertSame([], $draft->warnings);
        self::assertSame(
            [
                ['payer:contribution', 'Familie: Elternteil', 4000, true],
                ['payer:work_assignment_surcharge', 'Arbeitseinsatz-Zuschlag', 1500, true],
                ['payer:charge:charge-new', 'Beitrittsgebühr Familie (berechnet am 14.08.2026)', 2000, true],
                ['payer:charge:charge-old', 'Altgebühr (berechnet am 10.01.2024)', 500, false],
                ['child:contribution', 'Familie: Kind (zahlend)', 3000, true],
            ],
            array_map(static fn (DirectDebitPosition $position): array => [$position->id, $position->label, $position->amountCents, $position->selectedByDefault], $draft->positions),
        );
        self::assertSame('MANDAT-1 DE98ZZZ09999999999 Waldbad Borkheide e.V.', $draft->defaultRemittanceInformation);
        // Nächste Buchung 03/2027, Zahltag 01. — der 01.03.2027 ist ein Montag.
        self::assertSame('2027-03-01', $draft->defaultCollectionDate->format('Y-m-d'));
    }

    public function testPayerComesFirstFollowedByTheOthersFromOldestToYoungest(): void
    {
        $payer = $this->member('payer', mandateValidFrom: '2020-03-01', birthDate: '1990-01-01');
        $youngest = $this->member('youngest', payerId: 'payer', birthDate: '2015-06-01');
        $oldest = $this->member('oldest', payerId: 'payer', birthDate: '1960-02-02');
        $middle = $this->member('middle', payerId: 'payer', birthDate: '2008-03-03');

        $draft = $this->planner([$payer, $youngest, $oldest, $middle])->plan('payer', new \DateTimeImmutable(self::NOW));

        self::assertSame(
            ['payer', 'oldest', 'middle', 'youngest'],
            array_values(array_unique(array_map(static fn (DirectDebitPosition $position): string => $position->memberId, $draft->positions))),
        );
    }

    public function testRecurringPositionsAreSplitByThePayersInterval(): void
    {
        $payer = $this->member('payer', interval: PaymentInterval::Quarterly, mandateValidFrom: '2020-03-01');

        $draft = $this->planner([$payer])->plan('payer', new \DateTimeImmutable(self::NOW));

        self::assertSame(1000, $draft->positions[0]->amountCents);
        self::assertSame(4000, $draft->positions[0]->annualAmountCents);
    }

    public function testBlocksPlaceholderIbanExpiredMandateAndIncompleteCreditor(): void
    {
        $payer = $this->member('payer', iban: 'DE36000000000000000000', mandateValidUntil: '2026-01-31');

        $draft = $this->planner([$payer], DirectDebitCreditor::empty())->plan('payer', new \DateTimeImmutable(self::NOW));

        self::assertCount(3, $draft->blockers);
        self::assertStringContainsString('Gläubigerdaten', $draft->blockers[0]);
        self::assertStringContainsString('keine echte Bankverbindung', $draft->blockers[1]);
        self::assertStringContainsString('abgelaufen', $draft->blockers[2]);
    }

    public function testFallsBackToJoinDateWhenMandateDateIsMissing(): void
    {
        $payer = $this->member('payer');

        $draft = $this->planner([$payer])->plan('payer', new \DateTimeImmutable(self::NOW));

        self::assertSame('2020-01-01', $draft->mandateSignedOn->format('Y-m-d'));
        self::assertStringContainsString('Eintrittsdatum 01.01.2020', $draft->warnings[0]);
    }

    public function testCollectionDateFollowsTheNextBookingAndThePaymentDay(): void
    {
        $payer = $this->member('payer', paymentDay: PaymentDay::Fifteenth, mandateValidFrom: '2020-03-01');

        $draft = $this->planner([$payer])->plan('payer', new \DateTimeImmutable(self::NOW));

        self::assertSame('2027-03-15', $draft->defaultCollectionDate->format('Y-m-d'));
    }

    public function testOverdueNextBookingFallsBackToTheNextPossiblePaymentDay(): void
    {
        // Nächste Buchung 03/2026 liegt zurück — nächster 01. mit zwei Tagen Vorlauf ist der 01.10.2026.
        $payer = $this->member('payer', mandateValidFrom: '2020-03-01', nextBookingYear: 2026);

        $draft = $this->planner([$payer])->plan('payer', new \DateTimeImmutable(self::NOW));

        self::assertSame('2026-10-01', $draft->defaultCollectionDate->format('Y-m-d'));
    }

    public function testCollectionDateOnAWeekendMovesToMonday(): void
    {
        // Ohne nächste Buchung: nächster 15. ab 13.11.2026 + 2 Tage ist Sonntag, der 15.11.2026.
        $payer = $this->member('payer', paymentDay: PaymentDay::Fifteenth, mandateValidFrom: '2020-03-01', nextBookingYear: null);

        $draft = $this->planner([$payer])->plan('payer', new \DateTimeImmutable('2026-11-13 09:00:00'));

        self::assertSame('2026-11-16', $draft->defaultCollectionDate->format('Y-m-d'));
    }

    public function testMemberWhoJoinedThisYearGetsAFirstDebitForTheJoiningYearRightAway(): void
    {
        // Antrag angenommen im Sept. 2026: nächste Buchung steht auf 03/2027, der Beitrag 2026 fehlt noch.
        $payer = $this->member('payer', paymentDay: PaymentDay::Fifteenth, joinedAt: '2026-09-01');

        $draft = $this->planner([$payer])->plan('payer', new \DateTimeImmutable(self::NOW));

        self::assertTrue($draft->joiningYearDebit);
        self::assertSame(2026, $draft->contributionYear);
        self::assertSame(SequenceType::First, $draft->defaultSequenceType);
        self::assertSame('2026-10-15', $draft->defaultCollectionDate->format('Y-m-d'));
        self::assertNull($draft->lastRecord);
    }

    public function testAfterTheJoiningYearDebitTheNextBookingFollowsAsRecurringDebit(): void
    {
        $payer = $this->member('payer', paymentDay: PaymentDay::Fifteenth, joinedAt: '2026-09-01');
        $joiningDebit = $this->record(2026, SequenceType::First);

        $draft = $this->planner([$payer], records: [$joiningDebit])->plan('payer', new \DateTimeImmutable(self::NOW));

        self::assertFalse($draft->joiningYearDebit);
        self::assertSame(2027, $draft->contributionYear);
        self::assertSame(SequenceType::Recurring, $draft->defaultSequenceType);
        self::assertSame('2027-03-15', $draft->defaultCollectionDate->format('Y-m-d'));
        self::assertSame($joiningDebit, $draft->lastRecord);
    }

    public function testImportedMemberWithoutHistoryGetsARecurringDebit(): void
    {
        $payer = $this->member('payer', mandateValidFrom: '2015-03-01');

        $draft = $this->planner([$payer])->plan('payer', new \DateTimeImmutable(self::NOW));

        self::assertFalse($draft->joiningYearDebit);
        self::assertSame(SequenceType::Recurring, $draft->defaultSequenceType);
    }

    public function testWarnsWhenTheContributionYearWasAlreadyDebited(): void
    {
        $payer = $this->member('payer', mandateValidFrom: '2015-03-01');

        $draft = $this->planner([$payer], records: [$this->record(2027, SequenceType::Recurring)])->plan('payer', new \DateTimeImmutable(self::NOW));

        self::assertStringContainsString('Für 2027 wurde bereits', $draft->warnings[0]);
    }

    private function record(int $contributionYear, SequenceType $sequenceType): DirectDebitRecord
    {
        return new DirectDebitRecord('record', 'payer', 'MANDAT-1', $contributionYear, $sequenceType, new \DateTimeImmutable('2026-10-15'), 7500, 'WB-1', new \DateTimeImmutable('2026-09-20 10:00:00'));
    }

    /**
     * @param list<Member>            $members
     * @param list<DirectDebitRecord> $records
     */
    private function planner(array $members, ?DirectDebitCreditor $creditor = null, array $records = []): PayerDirectDebitPlanner
    {
        $byId = [];
        foreach ($members as $member) {
            $byId[$member->id] = $member;
        }
        $memberManager = $this->createStub(MemberManagerInterface::class);
        $memberManager->method('get')->willReturnCallback(static fn (string $id): Member => $byId[$id]);
        $memberManager->method('findByPayerMemberId')->willReturnCallback(
            static fn (string $payerId): array => array_values(array_filter($members, static fn (Member $member): bool => $member->payerMemberId === $payerId)),
        );

        $labels = [
            ContributionCategory::FamilyAdult->value => 'Familie: Elternteil',
            ContributionCategory::FamilyChildPaying->value => 'Familie: Kind (zahlend)',
        ];
        $rates = $this->createStub(ContributionRateManagerInterface::class);
        $rates->method('findByCategory')->willReturnCallback(
            static fn (ContributionCategory $category): ?ContributionRate => isset($labels[$category->value])
                ? new ContributionRate($category->value, $category, $labels[$category->value], 0, PaymentInterval::Yearly)
                : null,
        );

        $creditors = $this->createStub(DirectDebitCreditorManagerInterface::class);
        $creditors->method('get')->willReturn($creditor ?? new DirectDebitCreditor('Waldbad Borkheide e.V.', 'DE98ZZZ09999999999', 'DE02120300000000202051', null));

        $recordManager = $this->createStub(DirectDebitRecordManagerInterface::class);
        $recordManager->method('findByPayerMemberId')->willReturn($records);

        return new PayerDirectDebitPlanner($memberManager, $rates, $creditors, $recordManager);
    }

    /**
     * @param list<ContributionCharge> $charges
     */
    private function member(
        string $id,
        ?string $payerId = null,
        ContributionCategory $category = ContributionCategory::FamilyAdult,
        int $amountCents = 4000,
        ?int $surchargeCents = 1500,
        ?string $leftAt = null,
        bool $liable = true,
        string $iban = 'DE89370400440532013000',
        PaymentInterval $interval = PaymentInterval::Yearly,
        PaymentDay $paymentDay = PaymentDay::First,
        ?string $mandateValidFrom = null,
        ?string $mandateValidUntil = null,
        array $charges = [],
        string $birthDate = '1985-05-05',
        ?int $nextBookingYear = 2027,
        string $joinedAt = '2020-01-01',
    ): Member {
        $isPayer = $payerId === null;

        return new Member(
            id: $id,
            memberNumber: 'Bad-'.$id,
            primaryMemberNumber: 'Bad-payer',
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: ucfirst($id),
            birthDate: new \DateTimeImmutable($birthDate),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: $isPayer ? FamilyRole::Head : FamilyRole::Child,
            joinedAt: new \DateTimeImmutable($joinedAt),
            leftAt: $leftAt === null ? null : new \DateTimeImmutable($leftAt),
            function: $liable ? MemberFunction::Member : MemberFunction::Board,
            accountHolder: $isPayer ? 'Payer Muster' : null,
            iban: $isPayer ? $iban : null,
            bankName: null,
            mandateReference: $isPayer ? 'MANDAT-1' : null,
            paymentMethod: PaymentMethod::SepaDirectDebit,
            paymentInterval: $interval,
            paymentDay: $paymentDay,
            payerType: $isPayer ? PayerType::SelfPayer : PayerType::OtherMember,
            payerMemberId: $payerId,
            nextBookingMonth: $nextBookingYear === null ? null : 3,
            nextBookingYear: $nextBookingYear,
            contributionCategory: $category,
            contributionAmountCents: $amountCents,
            workAssignmentSurchargeCents: $surchargeCents,
            remarks: [],
            oneTimeCharges: $charges,
            version: 0,
            contributionLiable: $liable,
            mandateValidFrom: $mandateValidFrom === null ? null : new \DateTimeImmutable($mandateValidFrom),
            mandateValidUntil: $mandateValidUntil === null ? null : new \DateTimeImmutable($mandateValidUntil),
        );
    }
}
