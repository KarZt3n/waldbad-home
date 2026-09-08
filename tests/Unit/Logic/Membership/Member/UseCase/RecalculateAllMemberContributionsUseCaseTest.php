<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Dto\ContributionOutcome;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
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

        $result = (new RecalculateAllMemberContributionsUseCase($members, $calculator, $clock))->execute();

        self::assertSame(2, $result->updated);
        self::assertCount(1, $result->errors);
        self::assertSame('M-0002', $result->errors[0]->memberNumber);
        self::assertSame('Kein passender Beitragssatz.', $result->errors[0]->message);
    }

    private function member(string $id, string $memberNumber): Member
    {
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
            function: MemberFunction::Member,
            accountHolder: 'Max Muster',
            iban: 'DE89370400440532013000',
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
        );
    }
}
