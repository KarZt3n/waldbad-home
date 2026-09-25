<?php

namespace App\Tests\Unit\Logic\Membership\Member\Query;

use App\Logic\Membership\Member\Dto\FindMatchingMemberRequest;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\Member\Query\FindMatchingMemberQuery;
use App\Logic\Membership\Member\Query\ListMemberContactsQuery;
use App\Logic\Membership\Member\MemberProviderInterface;
use App\Logic\Membership\Member\Service\MemberIdentityMatcher;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class FindMatchingMemberQueryTest extends TestCase
{
    public function testReturnsOnlyTheLinkRelevantDataOfTheMatchedMember(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([$this->member()]);
        $query = new FindMatchingMemberQuery(new MemberIdentityMatcher($members));

        $match = $query->execute(new FindMatchingMemberRequest('Erika', 'Musterfrau', new \DateTimeImmutable('1990-01-01')));

        self::assertNotNull($match);
        self::assertSame('m1', $match->id);
        self::assertSame('Bad-m1', $match->memberNumber);
    }

    public function testReturnsNullWithoutUniqueMatch(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('search')->willReturn([]);
        $query = new FindMatchingMemberQuery(new MemberIdentityMatcher($members));

        self::assertNull($query->execute(new FindMatchingMemberRequest('Max', 'Mustermann', new \DateTimeImmutable('1980-05-05'))));
    }

    public function testMemberContactsAreReturnedByIdAndUnknownIdsAreSkipped(): void
    {
        $provider = $this->createStub(MemberProviderInterface::class);
        $provider->method('find')->willReturnCallback(fn (string $id): ?Member => $id === 'm1' ? $this->member() : null);

        $contacts = (new ListMemberContactsQuery($provider))->execute(['m1', 'unknown', 'm1']);

        self::assertSame(['m1'], array_keys($contacts));
        self::assertSame('Musterweg 1', $contacts['m1']->street);
        self::assertSame('14547 Borkheide', $contacts['m1']->postalCode.' '.$contacts['m1']->city);
    }

    private function member(): Member
    {
        return new Member(
            id: 'm1',
            memberNumber: 'Bad-m1',
            primaryMemberNumber: 'FAM-1',
            salutation: Salutation::Ms,
            lastName: 'Musterfrau',
            firstName: 'Erika',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            street: 'Musterweg 1',
            postalCode: '14547',
            city: 'Borkheide',
            email: null,
            phone: null,
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
