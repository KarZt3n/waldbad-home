<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Service;

use App\Logic\Event\HelpRequest\Service\EventHelpRequestRecipientResolver;
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

final class EventHelpRequestRecipientResolverTest extends TestCase
{
    public function testResolvesToMembersOwnEmailWhenPresent(): void
    {
        $member = $this->member('m1', 'FAM-1', 'erika@example.test');
        $members = $this->createStub(MemberManagerInterface::class);
        $resolver = new EventHelpRequestRecipientResolver($members);

        self::assertSame(['erika@example.test'], $resolver->resolve($member, null));
    }

    public function testFallsBackToHouseholdEmailsWhenMemberHasNoOwnEmail(): void
    {
        $member = $this->member('sally-1', 'FAM-1', null);
        $karsten = $this->member('karsten-1', 'FAM-1', 'karsten@example.test');
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByPrimaryMemberNumber')->willReturn([$member, $karsten]);
        $resolver = new EventHelpRequestRecipientResolver($members);

        self::assertSame(['karsten@example.test'], $resolver->resolve($member, null));
    }

    public function testAddsSubmittedEmailWhenItDiffersFromMembersOwnEmail(): void
    {
        $member = $this->member('m1', 'FAM-1', 'erika@example.test');
        $members = $this->createStub(MemberManagerInterface::class);
        $resolver = new EventHelpRequestRecipientResolver($members);

        $result = $resolver->resolve($member, 'andere-adresse@example.test');
        sort($result);

        self::assertSame(['andere-adresse@example.test', 'erika@example.test'], $result);
    }

    public function testDoesNotDuplicateWhenSubmittedEmailMatchesMembersOwnEmailCaseInsensitively(): void
    {
        $member = $this->member('m1', 'FAM-1', 'erika@example.test');
        $members = $this->createStub(MemberManagerInterface::class);
        $resolver = new EventHelpRequestRecipientResolver($members);

        self::assertSame(['erika@example.test'], $resolver->resolve($member, ' Erika@Example.test '));
    }

    public function testResolvesToSubmittedEmailWhenNoMemberWasMatched(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $resolver = new EventHelpRequestRecipientResolver($members);

        self::assertSame(['helfer@example.test'], $resolver->resolve(null, 'helfer@example.test'));
    }

    public function testResolvesToNothingWhenNeitherMemberNorEmailIsAvailable(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $resolver = new EventHelpRequestRecipientResolver($members);

        self::assertSame([], $resolver->resolve(null, null));
    }

    private function member(string $id, string $primaryMemberNumber, ?string $email): Member
    {
        return new Member(
            id: $id,
            memberNumber: 'Bad-'.$id,
            primaryMemberNumber: $primaryMemberNumber,
            salutation: Salutation::Mr,
            lastName: 'Musterfrau',
            firstName: 'Erika',
            birthDate: new \DateTimeImmutable('1990-06-15'),
            street: 'Musterweg 1',
            postalCode: '14547',
            city: 'Borkheide',
            email: $email,
            phone: null,
            familyRole: FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            active: true,
            function: MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: PaymentMethod::BankTransfer,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: PayerType::SelfPayer,
            payerMemberId: null,
            nextBookingMonth: 1,
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
