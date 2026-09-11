<?php

namespace App\Tests\Unit\Logic\Membership\MemberAccess\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateSettingsManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\ContributionRate\Model\ContributionRateSettings;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentDay;
use App\Logic\Membership\Member\Model\PaymentMethod;
use App\Logic\Membership\Member\Model\Salutation;
use App\Logic\Membership\MemberAccess\Exception\InvalidMemberAccessTokenException;
use App\Logic\Membership\MemberAccess\Dto\WorkAssignmentCreditResponse;
use App\Logic\Membership\MemberAccess\Manager\MemberAccessTokenManagerInterface;
use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;
use App\Logic\Membership\MemberAccess\Service\MemberAccessPasswordHasher;
use App\Logic\Membership\MemberAccess\Service\WorkAssignmentCreditCalculator;
use App\Logic\Membership\MemberAccess\UseCase\ResolveMemberAccessSessionUseCase;
use App\Logic\Membership\PaymentInterval;
use PHPUnit\Framework\TestCase;

final class ResolveMemberAccessSessionUseCaseTest extends TestCase
{
    private const string NOW = '2026-06-01T10:00:00+02:00';
    private const string PASSWORD = 'aB3!xy9?';

    public function testThrowsForAnUnknownToken(): void
    {
        $tokens = $this->createStub(MemberAccessTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn(null);

        $this->expectException(InvalidMemberAccessTokenException::class);

        $this->useCase($tokens)->execute('raw-token', self::PASSWORD);
    }

    public function testThrowsForAnExpiredToken(): void
    {
        $tokens = $this->createStub(MemberAccessTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token(new \DateTimeImmutable('2026-06-01T09:59:59+02:00')));

        $this->expectException(InvalidMemberAccessTokenException::class);

        $this->useCase($tokens)->execute('raw-token', self::PASSWORD);
    }

    /**
     * Derselbe Fehler wie bei einem unbekannten/abgelaufenen Token — es darf nicht erkennbar sein,
     * welcher der beiden Faktoren (Token/Passwort) nicht gepasst hat.
     */
    public function testThrowsForAWrongPassword(): void
    {
        $tokens = $this->createStub(MemberAccessTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token(new \DateTimeImmutable('2026-06-01T10:05:00+02:00')));

        $this->expectException(InvalidMemberAccessTokenException::class);

        $this->useCase($tokens)->execute('raw-token', 'wrong-password');
    }

    public function testThrowsWhenNoMemberInTheHouseholdExistsAnymore(): void
    {
        $tokens = $this->createStub(MemberAccessTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token(new \DateTimeImmutable('2026-06-01T10:05:00+02:00')));
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByPrimaryMemberNumber')->willReturn([]);

        $this->expectException(InvalidMemberAccessTokenException::class);

        $this->useCase($tokens, $members)->execute('raw-token', self::PASSWORD);
    }

    /**
     * Der Token wird über den SHA-256-Hash des übergebenen Klartext-Tokens nachgeschlagen, der
     * Beitragssatz wird für die Anzeige über die Kategorie des Mitglieds aufgelöst, und das
     * gemeinsame „gültig ab" (`ContributionRateSettings`) landet als `contributionRatesValidFrom`
     * auf der Session.
     */
    public function testReturnsTheHouseholdWithResolvedContributionRateLabel(): void
    {
        $tokens = $this->createMock(MemberAccessTokenManagerInterface::class);
        $tokens->expects(self::once())->method('findByHash')
            ->willReturnCallback(function (string $hash) {
                self::assertSame(hash('sha256', 'raw-token'), $hash);

                return $this->token(new \DateTimeImmutable('2026-06-01T10:05:00+02:00'));
            });

        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByPrimaryMemberNumber')->willReturn([$this->member('member-1', ContributionCategory::IndividualSenior)]);

        $rates = $this->createStub(ContributionRateManagerInterface::class);
        $rates->method('findByCategory')->willReturn(new ContributionRate('rate-1', ContributionCategory::IndividualSenior, 'Einzelperson über 21 Jahre', 5000, PaymentInterval::Yearly));

        $settings = $this->createStub(ContributionRateSettingsManagerInterface::class);
        $settings->method('get')->willReturn(new ContributionRateSettings(new \DateTimeImmutable('2026-01-01')));

        $credit = $this->createStub(WorkAssignmentCreditCalculator::class);
        $credit->method('calculate')->willReturn(new WorkAssignmentCreditResponse('2026-01-01', '2027-01-01', 0, 0, 5, 300, 0, 0));

        $session = $this->useCase($tokens, $members, $rates, $settings, $credit)->execute('raw-token', self::PASSWORD);

        self::assertSame('erika@example.test', $session->email);
        self::assertCount(1, $session->members);
        self::assertSame('Einzelperson über 21 Jahre', $session->members[0]->contributionCategoryLabel);
        self::assertSame('2026-01-01', $session->contributionRatesValidFrom);
        self::assertSame(0, $session->workAssignmentCredit->creditCents);
    }

    /**
     * Ist noch nie ein gemeinsames „gültig ab" gesetzt worden, bleibt das Feld null — das Frontend
     * blendet den Hinweis dann aus, statt ein leeres Datum anzuzeigen.
     */
    public function testContributionRatesValidFromIsNullWhenNeverSet(): void
    {
        $tokens = $this->createStub(MemberAccessTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token(new \DateTimeImmutable('2026-06-01T10:05:00+02:00')));

        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByPrimaryMemberNumber')->willReturn([$this->member('member-1', ContributionCategory::IndividualSenior)]);

        $settings = $this->createStub(ContributionRateSettingsManagerInterface::class);
        $settings->method('get')->willReturn(new ContributionRateSettings(null));

        $session = $this->useCase($tokens, $members, null, $settings)->execute('raw-token', self::PASSWORD);

        self::assertNull($session->contributionRatesValidFrom);
    }

    private function useCase(
        MemberAccessTokenManagerInterface $tokens,
        ?MemberManagerInterface $members = null,
        ?ContributionRateManagerInterface $rates = null,
        ?ContributionRateSettingsManagerInterface $settings = null,
        ?WorkAssignmentCreditCalculator $workAssignmentCredit = null,
    ): ResolveMemberAccessSessionUseCase {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable(self::NOW));

        $defaultCredit = $this->createStub(WorkAssignmentCreditCalculator::class);
        $defaultCredit->method('calculate')->willReturn(new WorkAssignmentCreditResponse('2026-01-01', '2027-01-01', 0, 0, 5, 300, 0, 0));

        return new ResolveMemberAccessSessionUseCase(
            $tokens,
            $members ?? $this->createStub(MemberManagerInterface::class),
            $rates ?? $this->createStub(ContributionRateManagerInterface::class),
            $settings ?? $this->createStub(ContributionRateSettingsManagerInterface::class),
            $workAssignmentCredit ?? $defaultCredit,
            new MemberAccessPasswordHasher(),
            $clock,
        );
    }

    private function token(\DateTimeImmutable $expiresAt): MemberAccessToken
    {
        return new MemberAccessToken(
            'token-1',
            'erika@example.test',
            hash('sha256', 'raw-token'),
            $expiresAt,
            'Bad-001',
            password_hash(self::PASSWORD, PASSWORD_DEFAULT),
        );
    }

    private function member(string $id, ?ContributionCategory $category): Member
    {
        return new Member(
            id: $id,
            memberNumber: 'Bad-001',
            primaryMemberNumber: 'Bad-001',
            salutation: Salutation::Ms,
            lastName: 'Musterfrau',
            firstName: 'Erika',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: 'erika@example.test',
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
            contributionCategory: $category,
            contributionAmountCents: 5000,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
        );
    }
}
