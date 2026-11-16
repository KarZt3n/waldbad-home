<?php

namespace App\Tests\Unit\Logic\Membership\Member\Service;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitRecordManagerInterface;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\Member\Service\PayerTransfer;
use App\Logic\Membership\MemberAccess\Manager\MemberAccessTokenManagerInterface;
use App\Logic\Membership\PaymentInterval;
use App\Logic\Membership\WorkAssignmentRefund\Manager\WorkAssignmentRefundRecordManagerInterface;
use PHPUnit\Framework\TestCase;

final class PayerTransferTest extends TestCase
{
    private const string PARENT_IBAN = 'DE89370400440532013000';
    private const string CHILD_IBAN = 'DE02120300000000202051';

    /** @var array<string, Member> */
    private array $saved = [];

    public function testSuccessorTakesOverBankAccountMandateAndDependentsOfADeletedHead(): void
    {
        $head = $this->head();
        $partner = $this->dependent('partner', 'Bad-00002', FamilyRole::Partner, '1982-01-01');
        $child = $this->dependent('child', 'Bad-00003', FamilyRole::Child, '2012-01-01');
        $directDebitRecords = $this->createMock(DirectDebitRecordManagerInterface::class);
        $directDebitRecords->expects(self::once())->method('reassignPayer')->with('head', 'partner');
        $refundRecords = $this->createMock(WorkAssignmentRefundRecordManagerInterface::class);
        $refundRecords->expects(self::once())->method('reassignPayer')->with('head', 'partner');
        $refundRecords->expects(self::once())->method('reassignHousehold')->with('Bad-00001', 'Bad-00002');
        $accessTokens = $this->createMock(MemberAccessTokenManagerInterface::class);
        $accessTokens->expects(self::once())->method('reassignHousehold')->with('Bad-00001', 'Bad-00002');
        $transfer = $this->transfer([$head, $partner, $child], $directDebitRecords, $refundRecords, $accessTokens);

        $successor = $transfer->transfer($head, 'partner', false, transferPrimaryMembership: true, previousStays: false);

        self::assertSame(PayerType::SelfPayer, $successor->payerType);
        self::assertNull($successor->payerMemberId);
        self::assertSame('Erika Muster', $successor->accountHolder);
        self::assertSame(self::PARENT_IBAN, $successor->iban);
        self::assertSame('WV-Bad-00001-00001', $successor->mandateReference);
        self::assertEquals(new \DateTimeImmutable('2020-01-01'), $successor->mandateValidFrom);
        self::assertSame(PaymentMethod::SepaDirectDebit, $successor->paymentMethod);
        self::assertSame(PaymentDay::Fifteenth, $successor->paymentDay);
        self::assertSame(2027, $successor->nextBookingYear);
        self::assertSame(FamilyRole::Head, $successor->familyRole);
        self::assertSame('Bad-00002', $successor->primaryMemberNumber);

        $savedChild = $this->saved['child'];
        self::assertSame(PayerType::OtherMember, $savedChild->payerType);
        self::assertSame('partner', $savedChild->payerMemberId);
        self::assertSame('Bad-00002', $savedChild->primaryMemberNumber);
        self::assertSame(FamilyRole::Child, $savedChild->familyRole);
        self::assertArrayNotHasKey('head', $this->saved);
        self::assertSame('partner', array_key_first($this->saved), 'Der Nachfolger muss vor seinen Zahlungsempfängern gespeichert werden.');
    }

    public function testPreviousPayerWhoStaysBecomesFamilyMemberPayingThroughTheSuccessor(): void
    {
        $head = $this->head(leftAt: '2025-12-31');
        $child = $this->dependent('child', 'Bad-00003', FamilyRole::Child, '2012-01-01');
        $transfer = $this->transfer([$head, $child]);

        $transfer->transfer($head, 'child', false, transferPrimaryMembership: true, previousStays: true);

        $savedHead = $this->saved['head'];
        self::assertSame(PayerType::OtherMember, $savedHead->payerType);
        self::assertSame('child', $savedHead->payerMemberId);
        self::assertNull($savedHead->iban);
        self::assertNull($savedHead->mandateReference);
        self::assertNull($savedHead->nextBookingYear);
        self::assertSame(PaymentMethod::NotSpecified, $savedHead->paymentMethod);
        self::assertSame(FamilyRole::Partner, $savedHead->familyRole);
        self::assertSame('Bad-00003', $savedHead->primaryMemberNumber);
        self::assertSame(FamilyRole::Head, $this->saved['child']->familyRole);
    }

    public function testPrimaryMembershipStaysWhenNotRequested(): void
    {
        $head = $this->head();
        $child = $this->dependent('child', 'Bad-00003', FamilyRole::Child, '2012-01-01');
        $refundRecords = $this->createMock(WorkAssignmentRefundRecordManagerInterface::class);
        $refundRecords->expects(self::never())->method('reassignHousehold');
        $accessTokens = $this->createMock(MemberAccessTokenManagerInterface::class);
        $accessTokens->expects(self::never())->method('reassignHousehold');
        $transfer = $this->transfer([$head, $child], refundRecords: $refundRecords, accessTokens: $accessTokens);

        $successor = $transfer->transfer($head, 'child', false, transferPrimaryMembership: false, previousStays: true);

        self::assertSame('Bad-00001', $successor->primaryMemberNumber);
        self::assertSame(FamilyRole::Child, $successor->familyRole);
        self::assertSame(FamilyRole::Head, $this->saved['head']->familyRole);
    }

    public function testSuccessorWhoAlreadyPaysHimselfCanKeepHisOwnPaymentDetails(): void
    {
        $head = $this->head();
        $adultChild = $this->selfPayingChild();
        $transfer = $this->transfer([$head, $adultChild]);

        $successor = $transfer->transfer($head, 'adult-child', true, transferPrimaryMembership: true, previousStays: false);

        self::assertSame(self::CHILD_IBAN, $successor->iban);
        self::assertSame('WV-Bad-00004-00001', $successor->mandateReference);
    }

    public function testSuccessorWithoutOwnPaymentDetailsCannotKeepThem(): void
    {
        $head = $this->head();
        $child = $this->dependent('child', 'Bad-00003', FamilyRole::Child, '2012-01-01');
        $transfer = $this->transfer([$head, $child]);

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('keine eigenen Zahlungsdaten');
        $transfer->transfer($head, 'child', true, transferPrimaryMembership: true, previousStays: false);
    }

    public function testHeadWhoDoesNotPayOnlyHandsOverThePrimaryMembership(): void
    {
        $head = $this->dependent('head', 'Bad-00001', FamilyRole::Head, '1980-01-01', payerId: 'partner', primaryMemberNumber: 'Bad-00001');
        $partner = $this->selfPayingChild('partner', 'Bad-00002', FamilyRole::Partner);
        $directDebitRecords = $this->createMock(DirectDebitRecordManagerInterface::class);
        $directDebitRecords->expects(self::never())->method('reassignPayer');
        $refundRecords = $this->createMock(WorkAssignmentRefundRecordManagerInterface::class);
        $refundRecords->expects(self::never())->method('reassignPayer');
        $transfer = $this->transfer([$head, $partner], $directDebitRecords, $refundRecords);

        $successor = $transfer->transfer($head, 'partner', false, transferPrimaryMembership: true, previousStays: false);

        self::assertSame(self::CHILD_IBAN, $successor->iban);
        self::assertSame(FamilyRole::Head, $successor->familyRole);
        self::assertSame('Bad-00002', $successor->primaryMemberNumber);
    }

    public function testRejectsAMemberThatIsNeitherPayerNorHead(): void
    {
        $head = $this->head();
        $child = $this->dependent('child', 'Bad-00003', FamilyRole::Child, '2012-01-01');
        $partner = $this->dependent('partner', 'Bad-00002', FamilyRole::Partner, '1982-01-01');
        $transfer = $this->transfer([$head, $child, $partner]);

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('weder Zahler noch Hauptmitglied');
        $transfer->transfer($child, 'partner', false, transferPrimaryMembership: true, previousStays: true);
    }

    public function testRejectsASuccessorFromAnotherFamily(): void
    {
        $head = $this->head();
        $stranger = $this->dependent('stranger', 'Bad-00009', FamilyRole::Child, '2012-01-01', primaryMemberNumber: 'Bad-00008');
        $transfer = $this->transfer([$head, $stranger]);

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('selben Familie');
        $transfer->transfer($head, 'stranger', false, transferPrimaryMembership: true, previousStays: false);
    }

    public function testRejectsASuccessorWhoHasLeft(): void
    {
        $head = $this->head();
        $partner = $this->dependent('partner', 'Bad-00002', FamilyRole::Partner, '1982-01-01', leftAt: '2025-12-31');
        $transfer = $this->transfer([$head, $partner]);

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('ausgetretenes Mitglied');
        $transfer->transfer($head, 'partner', false, transferPrimaryMembership: true, previousStays: false);
    }

    public function testRejectsTheMemberItselfAsSuccessor(): void
    {
        $head = $this->head();
        $transfer = $this->transfer([$head]);

        $this->expectException(BusinessRuleViolationException::class);
        $transfer->transfer($head, 'head', false, transferPrimaryMembership: true, previousStays: false);
    }

    /**
     * @param list<Member> $members
     */
    private function transfer(
        array $members,
        ?DirectDebitRecordManagerInterface $directDebitRecords = null,
        ?WorkAssignmentRefundRecordManagerInterface $refundRecords = null,
        ?MemberAccessTokenManagerInterface $accessTokens = null,
    ): PayerTransfer {
        $byId = [];
        foreach ($members as $member) {
            $byId[$member->id] = $member;
        }
        $manager = $this->createStub(MemberManagerInterface::class);
        $manager->method('get')->willReturnCallback(static fn (string $id): Member => $byId[$id]);
        $manager->method('findByPayerMemberId')->willReturnCallback(static fn (string $payerId): array => array_values(array_filter(
            $members,
            static fn (Member $member): bool => $member->payerMemberId === $payerId,
        )));
        $manager->method('findByPrimaryMemberNumber')->willReturnCallback(static fn (string $number): array => array_values(array_filter(
            $members,
            static fn (Member $member): bool => $member->primaryMemberNumber === $number,
        )));
        $manager->method('save')->willReturnCallback(function (Member $member): Member {
            $this->saved[$member->id] = $member;

            return $member;
        });
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        return new PayerTransfer(
            $manager,
            $directDebitRecords ?? $this->createStub(DirectDebitRecordManagerInterface::class),
            $refundRecords ?? $this->createStub(WorkAssignmentRefundRecordManagerInterface::class),
            $accessTokens ?? $this->createStub(MemberAccessTokenManagerInterface::class),
            $clock,
        );
    }

    private function head(?string $leftAt = null): Member
    {
        return $this->member(
            id: 'head',
            memberNumber: 'Bad-00001',
            primaryMemberNumber: 'Bad-00001',
            familyRole: FamilyRole::Head,
            birthDate: '1980-01-01',
            leftAt: $leftAt,
            accountHolder: 'Erika Muster',
            iban: self::PARENT_IBAN,
            mandateReference: 'WV-Bad-00001-00001',
            paymentMethod: PaymentMethod::SepaDirectDebit,
            payerType: PayerType::SelfPayer,
            payerMemberId: null,
            nextBookingYear: 2027,
        );
    }

    private function selfPayingChild(string $id = 'adult-child', string $memberNumber = 'Bad-00004', FamilyRole $familyRole = FamilyRole::None): Member
    {
        return $this->member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: 'Bad-00001',
            familyRole: $familyRole,
            birthDate: '2000-01-01',
            leftAt: null,
            accountHolder: 'Max Muster',
            iban: self::CHILD_IBAN,
            mandateReference: 'WV-Bad-00004-00001',
            paymentMethod: PaymentMethod::SepaDirectDebit,
            payerType: PayerType::SelfPayer,
            payerMemberId: null,
            nextBookingYear: 2027,
        );
    }

    private function dependent(
        string $id,
        string $memberNumber,
        FamilyRole $familyRole,
        string $birthDate,
        ?string $leftAt = null,
        string $payerId = 'head',
        string $primaryMemberNumber = 'Bad-00001',
    ): Member {
        return $this->member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: $primaryMemberNumber,
            familyRole: $familyRole,
            birthDate: $birthDate,
            leftAt: $leftAt,
            accountHolder: null,
            iban: null,
            mandateReference: null,
            paymentMethod: PaymentMethod::NotSpecified,
            payerType: PayerType::OtherMember,
            payerMemberId: $payerId,
            nextBookingYear: null,
        );
    }

    private function member(
        string $id,
        string $memberNumber,
        string $primaryMemberNumber,
        FamilyRole $familyRole,
        string $birthDate,
        ?string $leftAt,
        ?string $accountHolder,
        ?string $iban,
        ?string $mandateReference,
        PaymentMethod $paymentMethod,
        PayerType $payerType,
        ?string $payerMemberId,
        ?int $nextBookingYear,
    ): Member {
        return new Member(
            id: $id,
            memberNumber: $memberNumber,
            primaryMemberNumber: $primaryMemberNumber,
            salutation: Salutation::Diverse,
            lastName: 'Muster',
            firstName: 'Max',
            birthDate: new \DateTimeImmutable($birthDate),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: null,
            phone: null,
            familyRole: $familyRole,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: $leftAt === null ? null : new \DateTimeImmutable($leftAt),
            function: MemberFunction::Member,
            accountHolder: $accountHolder,
            iban: $iban,
            bankName: null,
            mandateReference: $mandateReference,
            paymentMethod: $paymentMethod,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::Fifteenth,
            payerType: $payerType,
            payerMemberId: $payerMemberId,
            nextBookingMonth: $nextBookingYear === null ? null : 3,
            nextBookingYear: $nextBookingYear,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 1,
            mandateValidFrom: $mandateReference === null ? null : new \DateTimeImmutable('2020-01-01'),
        );
    }
}
