<?php

namespace App\Tests\Unit\Logic\Event\HelpRequest\Service;

use App\Logic\Event\HelpRequest\Service\EventHelpRequestMemberMatcher;
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

final class EventHelpRequestMemberMatcherTest extends TestCase
{
    public function testMatchesOnNameAloneIgnoringCaseAndWhitespace(): void
    {
        $member = $this->member('m1', 'Erika', 'Musterfrau', 'erika@example.test', '1990-01-01');
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([$member]);
        $matcher = new EventHelpRequestMemberMatcher($members);

        $match = $matcher->match('  ERIKA ', ' musterfrau  ', null, null);

        self::assertSame($member, $match);
    }

    public function testDoesNotMatchWhenNoCandidateHasThatExactName(): void
    {
        $member = $this->member('m1', 'Erika', 'Musterfrau', null, '1990-01-01');
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([$member]);
        $matcher = new EventHelpRequestMemberMatcher($members);

        self::assertNull($matcher->match('Erik', 'Musterfrau', null, null));
    }

    public function testAmbiguousNameWithoutEmailOrBirthDateStaysUnmatched(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([
            $this->member('m1', 'Erika', 'Musterfrau', 'erika@example.test', '1990-01-01'),
            $this->member('m2', 'Erika', 'Musterfrau', 'erika2@example.test', '1991-02-02'),
        ]);
        $matcher = new EventHelpRequestMemberMatcher($members);

        self::assertNull($matcher->match('Erika', 'Musterfrau', null, null));
    }

    public function testAmbiguousNameIsNarrowedDownByEmail(): void
    {
        $wanted = $this->member('m2', 'Erika', 'Musterfrau', 'erika2@example.test', '1991-02-02');
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([
            $this->member('m1', 'Erika', 'Musterfrau', 'erika@example.test', '1990-01-01'),
            $wanted,
        ]);
        $matcher = new EventHelpRequestMemberMatcher($members);

        self::assertSame($wanted, $matcher->match('Erika', 'Musterfrau', ' Erika2@Example.test ', null));
    }

    public function testAmbiguousNameAndEmailIsNarrowedDownByBirthDate(): void
    {
        $wanted = $this->member('m2', 'Erika', 'Musterfrau', 'geteilt@example.test', '1991-02-02');
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([
            $this->member('m1', 'Erika', 'Musterfrau', 'geteilt@example.test', '1990-01-01'),
            $wanted,
        ]);
        $matcher = new EventHelpRequestMemberMatcher($members);

        $match = $matcher->match('Erika', 'Musterfrau', 'geteilt@example.test', new \DateTimeImmutable('1991-02-02'));

        self::assertSame($wanted, $match);
    }

    private function member(string $id, string $firstName, string $lastName, ?string $email, string $birthDate): Member
    {
        return new Member(
            id: $id,
            memberNumber: 'Bad-'.$id,
            primaryMemberNumber: 'Bad-'.$id,
            salutation: Salutation::Mr,
            lastName: $lastName,
            firstName: $firstName,
            birthDate: new \DateTimeImmutable($birthDate),
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
