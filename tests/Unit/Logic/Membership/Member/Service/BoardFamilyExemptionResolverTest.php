<?php

namespace App\Tests\Unit\Logic\Membership\Member\Service;

use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\Member\Service\BoardFamilyExemptionResolver;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class BoardFamilyExemptionResolverTest extends TestCase
{
    public function testHasBoardMemberIsTrueOnlyWhenAMemberHasTheBoardFunction(): void
    {
        $resolver = new BoardFamilyExemptionResolver();

        self::assertFalse($resolver->hasBoardMember([$this->member('a'), $this->member('b')]));
        self::assertTrue($resolver->hasBoardMember([$this->member('a'), $this->member('b', MemberFunction::Board)]));
    }

    public function testCorrectMakesLiableMembersExemptWhenHouseholdHasABoardMember(): void
    {
        $liable = $this->member('a', MemberFunction::Member, contributionLiable: true);
        $alreadyExempt = $this->member('b', MemberFunction::Member, contributionLiable: false);
        $resolver = new BoardFamilyExemptionResolver();

        self::assertFalse($resolver->correct($liable, true)->contributionLiable);
        // Ein bereits nicht beitragspflichtiges Mitglied wird nicht unnötig durch eine neue Instanz
        // ersetzt.
        self::assertSame($alreadyExempt, $resolver->correct($alreadyExempt, true));
    }

    public function testCorrectReactivatesExemptMembersWhenHouseholdHasNoBoardMember(): void
    {
        // Z. B. ein Familienmitglied, das nur wegen eines inzwischen ehemaligen Vorstands im
        // Haushalt noch als beitragsfrei gespeichert ist.
        $stillExempt = $this->member('a', MemberFunction::Member, contributionLiable: false);
        $alreadyLiable = $this->member('b', MemberFunction::Member, contributionLiable: true);
        $resolver = new BoardFamilyExemptionResolver();

        self::assertTrue($resolver->correct($stillExempt, false)->contributionLiable);
        self::assertSame($alreadyLiable, $resolver->correct($alreadyLiable, false));
    }

    private function member(
        string $id,
        MemberFunction $function = MemberFunction::Member,
        bool $contributionLiable = true,
    ): Member {
        return new Member(
            id: $id,
            memberNumber: $id,
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
            function: $function,
            accountHolder: 'Max Muster',
            iban: 'DE89370400440532013000',
            bankName: null,
            mandateReference: $id,
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
