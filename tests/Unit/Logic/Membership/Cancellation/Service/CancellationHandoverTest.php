<?php

namespace App\Tests\Unit\Logic\Membership\Cancellation\Service;

use App\Logic\Membership\Cancellation\Model\MemberCancellation;
use App\Logic\Membership\Cancellation\Model\MemberCancellationStatus;
use App\Logic\Membership\Cancellation\Service\CancellationHandover;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, PayerType, PaymentDay, PaymentMethod, Salutation};
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class CancellationHandoverTest extends TestCase
{
    public function testPayerWithDependentsNeedsSuccessorAndContactRecipient(): void
    {
        $parent = $this->member('parent', 'Bad-001', email: 'eltern@example.test');
        $child = $this->member('child', 'Bad-002', payerMemberId: 'parent');

        $candidates = CancellationHandover::candidates([$parent], [$parent, $child], []);

        self::assertSame([$child], $candidates);
        self::assertTrue(CancellationHandover::requiresSuccessor($parent, $candidates));
        self::assertTrue(CancellationHandover::requiresContactRecipient([$parent], $candidates));
    }

    public function testChildNeedsNoHandoverAndMembersWithAPendingCancellationAreNoCandidates(): void
    {
        $parent = $this->member('parent', 'Bad-001', email: 'eltern@example.test');
        $child = $this->member('child', 'Bad-002', payerMemberId: 'parent');
        $sibling = $this->member('sibling', 'Bad-003', payerMemberId: 'parent');
        $open = ['sibling' => $this->openCancellation('sibling')];

        $candidates = CancellationHandover::candidates([$child], [$parent, $child, $sibling], $open);

        self::assertSame([$parent], $candidates);
        self::assertFalse(CancellationHandover::requiresSuccessor($child, $candidates));
        self::assertFalse(CancellationHandover::requiresContactRecipient([$child], $candidates));
    }

    public function testNoContactRecipientNeededWhileSomeoneElseIsReachable(): void
    {
        $parent = $this->member('parent', 'Bad-001', email: 'eltern@example.test');
        $child = $this->member('child', 'Bad-002', payerMemberId: 'parent', phone: '0170 1');

        self::assertFalse(CancellationHandover::requiresContactRecipient([$parent], [$child]));
    }

    /**
     * Wird der Zahler mitsamt allen, für die er zahlt, gekündigt, bleibt niemand übrig — keine Übergabe.
     */
    public function testCancellingTheWholeGroupNeedsNoHandover(): void
    {
        $parent = $this->member('parent', 'Bad-001', email: 'eltern@example.test');
        $child = $this->member('child', 'Bad-002', payerMemberId: 'parent');

        $candidates = CancellationHandover::candidates([$parent, $child], [$parent, $child], []);

        self::assertSame([], $candidates);
        self::assertFalse(CancellationHandover::requiresSuccessor($parent, $candidates));
        self::assertFalse(CancellationHandover::requiresContactRecipient([$parent, $child], $candidates));
    }

    private function openCancellation(string $memberId): MemberCancellation
    {
        return new MemberCancellation(
            id: 'c-'.$memberId,
            memberId: $memberId,
            memberNumber: 'Bad-003',
            memberName: 'Max Muster',
            primaryMemberNumber: 'Bad-001',
            requestedByEmail: 'eltern@example.test',
            requestedByName: 'Max Muster',
            requestedByMemberNumber: 'Bad-001',
            submittedAt: new \DateTimeImmutable('2026-05-01'),
            exitDate: new \DateTimeImmutable('2026-12-31'),
            successorMemberId: null,
            successorName: null,
            contactRecipientMemberId: null,
            contactRecipientName: null,
            status: MemberCancellationStatus::Open,
            decidedAt: null,
        );
    }

    private function member(string $id, string $memberNumber, ?string $payerMemberId = null, ?string $email = null, ?string $phone = null): Member
    {
        return new Member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: 'Bad-001',
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: 'Max',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: $email,
            phone: $phone,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            function: MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: PaymentMethod::BankTransfer,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: $payerMemberId === null ? PayerType::SelfPayer : PayerType::OtherMember,
            payerMemberId: $payerMemberId,
            nextBookingMonth: 1,
            nextBookingYear: 2027,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
        );
    }
}
