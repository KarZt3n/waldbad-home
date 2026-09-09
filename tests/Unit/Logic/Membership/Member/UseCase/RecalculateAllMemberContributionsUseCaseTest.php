<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Dto\ContributionOutcome;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\Member\Service\BoardFamilyExemptionResolver;
use App\Logic\Membership\Member\Service\MemberContributionCalculator;
use App\Logic\Membership\Member\UseCase\RecalculateAllMemberContributionsUseCase;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class RecalculateAllMemberContributionsUseCaseTest extends TestCase
{
    public function testRecalculatesEveryHouseholdExactlyOnceAndCollectsErrors(): void
    {
        // Zwei Mitglieder derselben Hauptnummer (ein Haushalt) und ein alleinstehendes drittes,
        // das fachlich fehlschlägt (kein passender Beitragssatz) — der Fehler darf die anderen
        // beiden nicht verhindern.
        $memberA = $this->member('member-a', 'M-0001');
        $memberB = $this->member('member-b', 'M-0001');
        $memberC = $this->member('member-c', 'M-0002');

        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('search')->willReturn([$memberA, $memberB, $memberC]);
        $members->expects(self::exactly(2))->method('save')->willReturnCallback(
            static fn (Member $member): Member => $member,
        );

        $calculator = $this->createStub(MemberContributionCalculator::class);
        $calculator->method('calculate')->willReturnCallback(
            function (Member $candidate) {
                if ($candidate->id === 'member-c') {
                    throw new BusinessRuleViolationException('Kein passender Beitragssatz.');
                }

                return new ContributionOutcome(ContributionCategory::IndividualSenior, 5000, null);
            },
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        $result = (new RecalculateAllMemberContributionsUseCase($members, $calculator, $clock, new BoardFamilyExemptionResolver()))->execute();

        self::assertSame(2, $result->updated);
        self::assertCount(1, $result->errors);
        self::assertSame('M-0002', $result->errors[0]->memberNumber);
        self::assertSame('Kein passender Beitragssatz.', $result->errors[0]->message);
    }

    public function testHouseholdWithBoardMemberIsExemptFromContributionRegardlessOfStoredValue(): void
    {
        // Vorstandsmitglied (Kopf) war noch als beitragspflichtig gespeichert (z. B. weil die
        // Funktion nicht über das Bearbeitungsformular, sondern per Import gesetzt wurde) — die
        // Neuberechnung korrigiert das für den ganzen Haushalt, ohne den Beitragsrechner überhaupt
        // erst zu befragen.
        $board = $this->member('board', 'M-0001', MemberFunction::Board, contributionLiable: true);
        $partner = $this->member('partner', 'M-0001', MemberFunction::Member, contributionLiable: true);

        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('search')->willReturn([$board, $partner]);
        $saved = [];
        $members->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (Member $member) use (&$saved): Member {
                $saved[] = $member;

                return $member;
            },
        );

        $calculator = $this->createMock(MemberContributionCalculator::class);
        $calculator->expects(self::exactly(2))->method('calculate')->willReturnCallback(
            static function (Member $candidate) {
                self::assertFalse($candidate->contributionLiable);

                return new ContributionOutcome(null, 0, null);
            },
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        $result = (new RecalculateAllMemberContributionsUseCase($members, $calculator, $clock, new BoardFamilyExemptionResolver()))->execute();

        self::assertSame(2, $result->updated);
        self::assertCount(2, $saved);
        foreach ($saved as $member) {
            self::assertFalse($member->contributionLiable);
        }
    }

    public function testHouseholdWithoutBoardMemberAnymoreIsFullyReactivated(): void
    {
        // Ein ehemaliges Vorstandsmitglied ist jetzt einfaches Mitglied, sein Partner ist aber noch
        // als beitragsfrei gespeichert (Altdaten). Ohne verbleibendes Vorstandsmitglied im Haushalt
        // muss der Sammel-Lauf auch den Partner reaktivieren, nicht nur den Datensatz, der gerade
        // bearbeitet wurde.
        $formerBoard = $this->member('former-board', 'M-0001', MemberFunction::Member, contributionLiable: true);
        $stillExemptPartner = $this->member('partner', 'M-0001', MemberFunction::Member, contributionLiable: false);

        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('search')->willReturn([$formerBoard, $stillExemptPartner]);
        $saved = [];
        $members->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (Member $member) use (&$saved): Member {
                $saved[$member->id] = $member;

                return $member;
            },
        );

        $calculator = $this->createMock(MemberContributionCalculator::class);
        $calculator->expects(self::exactly(2))->method('calculate')->willReturnCallback(
            static function (Member $candidate) {
                self::assertTrue($candidate->contributionLiable);

                return new ContributionOutcome(ContributionCategory::IndividualSenior, 5000, null);
            },
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        (new RecalculateAllMemberContributionsUseCase($members, $calculator, $clock, new BoardFamilyExemptionResolver()))->execute();

        self::assertTrue($saved['partner']->contributionLiable);
    }

    public function testMemberThatCannotBeReactivatedIsSkippedAndReportedByMemberNumberWithoutAbortingOtherHouseholds(): void
    {
        // Ehemaliges Vorstandsmitglied ohne Vorstand mehr im Haushalt, aber als Selbstzahler mit
        // SEPA-Lastschrift ohne hinterlegte IBAN (z. B. weil nie benötigt, solange beitragsfrei) —
        // die Reaktivierung scheitert fachlich. Das darf weder den ganzen Lauf abbrechen noch den
        // anderen, unabhängigen Haushalt verhindern — und der Fehler muss erkennen lassen, wen er
        // betrifft (Mitgliedsnummer).
        $cannotReactivate = $this->member('broken', 'M-0001', contributionLiable: false, iban: null);
        $other = $this->member('other', 'M-0002');

        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('search')->willReturn([$cannotReactivate, $other]);
        $members->expects(self::once())->method('save')->willReturnCallback(
            static fn (Member $member): Member => $member,
        );

        $calculator = $this->createStub(MemberContributionCalculator::class);
        $calculator->method('calculate')->willReturn(new ContributionOutcome(ContributionCategory::IndividualSenior, 5000, null));

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        $result = (new RecalculateAllMemberContributionsUseCase($members, $calculator, $clock, new BoardFamilyExemptionResolver()))->execute();

        self::assertSame(1, $result->updated);
        self::assertCount(1, $result->errors);
        self::assertSame('M-0001', $result->errors[0]->memberNumber);
        self::assertStringContainsString('IBAN', $result->errors[0]->message);
    }

    private function member(
        string $id,
        string $memberNumber,
        MemberFunction $function = MemberFunction::Member,
        bool $contributionLiable = true,
        ?string $iban = 'DE89370400440532013000',
    ): Member {
        return new Member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: $memberNumber,
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
            function: $function,
            accountHolder: 'Max Muster',
            iban: $iban,
            bankName: null,
            mandateReference: $memberNumber,
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
            contributionLiable: $contributionLiable,
        );
    }
}
