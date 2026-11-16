<?php

namespace App\Tests\Unit\Logic\Membership\Member\UseCase;

use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Dto\CreateMemberRequest;
use App\Logic\Membership\Member\Dto\ImportRowError;
use App\Logic\Membership\Member\Dto\RestoreMemberRow;
use App\Logic\Membership\Member\Dto\RestoreMembersRequest;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\{FamilyRole, Member, MemberFunction, MemberSnapshot, PayerType, PaymentDay, PaymentMethod, Remark, Salutation};
use App\Logic\Membership\Member\UseCase\RestoreMembersUseCase;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class RestoreMembersUseCaseTest extends TestCase
{
    public function testWritesEveryRowExactlyAsExported(): void
    {
        $existing = $this->snapshot('id-1', 'M-1');
        $restored = [];
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findAllSnapshots')->willReturn([$existing]);
        $members->expects(self::once())->method('restoreAll')->willReturnCallback(static function (array $snapshots) use (&$restored): void {
            $restored = $snapshots;
        });
        $deletedAt = new \DateTimeImmutable('2026-05-01 12:00:00');

        $result = (new RestoreMembersUseCase($members))->execute(new RestoreMembersRequest([
            $this->row('id-1', 'M-1'),
            $this->row('id-2', 'M-2', payerMemberId: 'id-1', deletedAt: $deletedAt),
        ]));

        self::assertSame([1, 1, []], [$result->created, $result->updated, $result->errors]);
        self::assertCount(2, $restored);
        self::assertInstanceOf(MemberSnapshot::class, $restored[1]);
        self::assertSame('id-1', $restored[1]->member->payerMemberId);
        self::assertSame($deletedAt, $restored[1]->deletedAt);
        self::assertSame(ContributionCategory::IndividualSenior, $restored[1]->member->contributionCategory);
        self::assertSame(5000, $restored[1]->member->contributionAmountCents);
        self::assertTrue($restored[1]->member->emailConsent);
        self::assertSame('remark-id-2', $restored[1]->member->remarks[0]->id);
    }

    public function testRestoresNothingWhenARowConflicts(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->method('findAllSnapshots')->willReturn([$this->snapshot('other', 'M-1')]);
        $members->expects(self::never())->method('restoreAll');

        $result = (new RestoreMembersUseCase($members))->execute(new RestoreMembersRequest([
            $this->row('id-1', 'M-1'),
            $this->row('id-2', 'M-2', payerMemberId: 'unknown'),
            $this->row('id-2', 'M-3'),
        ]));

        self::assertSame(0, $result->created);
        self::assertSame(
            [
                [1, 'bereits an ein anderes Mitglied vergeben'],
                [2, 'weder in der Datei noch in der Datenbank'],
                [3, 'kommt mehrfach vor'],
            ],
            array_map(static fn (ImportRowError $error): array => [$error->rowNumber, self::fragment($error->message)], $result->errors),
        );
    }

    private static function fragment(string $message): string
    {
        foreach (['bereits an ein anderes Mitglied vergeben', 'weder in der Datei noch in der Datenbank', 'kommt mehrfach vor'] as $fragment) {
            if (str_contains($message, $fragment)) {
                return $fragment;
            }
        }

        return $message;
    }

    private function row(string $id, string $memberNumber, ?string $payerMemberId = null, ?\DateTimeImmutable $deletedAt = null): RestoreMemberRow
    {
        return new RestoreMemberRow(
            id: $id,
            memberNumber: $memberNumber,
            data: new CreateMemberRequest(
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
                function: MemberFunction::Member,
                accountHolder: null,
                iban: null,
                bankName: null,
                mandateReference: null,
                paymentMethod: PaymentMethod::NotSpecified,
                paymentInterval: PaymentInterval::Yearly,
                paymentDay: PaymentDay::First,
                payerType: $payerMemberId === null ? PayerType::SelfPayer : PayerType::OtherMember,
                payerMemberId: $payerMemberId,
                payerMemberNumber: null,
                nextBookingMonth: null,
                nextBookingYear: null,
            ),
            emailConsent: true,
            contributionCategory: ContributionCategory::IndividualSenior,
            contributionAmountCents: 5000,
            workAssignmentSurchargeCents: null,
            remarks: [new Remark('remark-'.$id, 'Hinweis', 'Admin', new \DateTimeImmutable('2026-02-01 10:00:00'))],
            oneTimeCharges: [],
            createdAt: new \DateTimeImmutable('2020-01-01 08:00:00'),
            updatedAt: new \DateTimeImmutable('2026-02-01 10:00:00'),
            deletedAt: $deletedAt,
        );
    }

    private function snapshot(string $id, string $memberNumber): MemberSnapshot
    {
        $member = new Member(
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
            function: MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: PaymentMethod::NotSpecified,
            paymentInterval: PaymentInterval::Yearly,
            paymentDay: PaymentDay::First,
            payerType: PayerType::SelfPayer,
            payerMemberId: null,
            nextBookingMonth: null,
            nextBookingYear: null,
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 1,
        );

        return new MemberSnapshot($member, new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-01'), null);
    }
}
