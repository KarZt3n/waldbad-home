<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\Member\UseCase\ExemptBoardMemberFamiliesUseCase;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class ExemptBoardMemberFamiliesUseCaseTest extends TestCase
{
    public function testHouseholdWithBoardMemberLosesContributionLiabilityForEveryone(): void
    {
        // Haushalt „M-0001“: Vorstandsmitglied (Kopf) + Partner, beide noch beitragspflichtig.
        // Zweiter, unabhängiger Haushalt „M-0002“ ohne Vorstandsmitglied bleibt unverändert.
        $board = $this->member('board', 'M-0001', function: MemberFunction::Board);
        $partner = $this->member('partner', 'M-0001', familyRole: FamilyRole::Partner);
        $unrelated = $this->member('unrelated', 'M-0002');

        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('search')->willReturn([$board, $partner, $unrelated]);
        $saved = [];
        $members->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (Member $member) use (&$saved): Member {
                $saved[] = $member;
                return $member;
            },
        );

        $result = (new ExemptBoardMemberFamiliesUseCase($members))->execute(true);

        self::assertSame(1, $result->householdsAffected);
        self::assertSame(['M-0001', 'M-0001'], $result->updatedMemberNumbers);
        self::assertCount(2, $saved);
        foreach ($saved as $member) {
            self::assertFalse($member->contributionLiable);
        }
    }

    public function testAlreadyExemptMemberIsNotSavedAgain(): void
    {
        $board = $this->member('board', 'M-0001', function: MemberFunction::Board, contributionLiable: false);
        $partner = $this->member('partner', 'M-0001', familyRole: FamilyRole::Partner);

        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('search')->willReturn([$board, $partner]);
        $members->expects(self::once())->method('save')->willReturnCallback(
            static function (Member $member): Member {
                self::assertSame('partner', $member->id);
                return $member;
            },
        );

        $result = (new ExemptBoardMemberFamiliesUseCase($members))->execute(true);

        self::assertSame(1, $result->householdsAffected);
        self::assertSame(['M-0001'], $result->updatedMemberNumbers);
    }

    public function testDryRunReportsWithoutSaving(): void
    {
        $board = $this->member('board', 'M-0001', function: MemberFunction::Board);

        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('search')->willReturn([$board]);
        $members->expects(self::never())->method('save');

        $result = (new ExemptBoardMemberFamiliesUseCase($members))->execute(false);

        self::assertSame(1, $result->householdsAffected);
        self::assertSame(['M-0001'], $result->updatedMemberNumbers);
    }

    public function testHouseholdWithoutBoardMemberIsUntouched(): void
    {
        $member = $this->member('member-a', 'M-0002');

        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('search')->willReturn([$member]);
        $members->expects(self::never())->method('save');

        $result = (new ExemptBoardMemberFamiliesUseCase($members))->execute(true);

        self::assertSame(0, $result->householdsAffected);
        self::assertSame([], $result->updatedMemberNumbers);
    }

    private function member(
        string $id,
        string $memberNumber,
        MemberFunction $function = MemberFunction::Member,
        FamilyRole $familyRole = FamilyRole::Head,
        bool $contributionLiable = true,
    ): Member {
        return new Member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: $memberNumber,
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: 'Max',
            birthDate: new \DateTimeImmutable('1980-01-01'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: $familyRole,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            active: true,
            function: $function,
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
            contributionLiable: $contributionLiable,
        );
    }
}
