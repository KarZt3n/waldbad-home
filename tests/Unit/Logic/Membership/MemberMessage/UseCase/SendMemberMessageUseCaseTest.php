<?php

namespace App\Tests\Unit\Logic\Membership\MemberMessage\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateSettingsManagerInterface;
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
use App\Logic\Membership\MemberAccess\Manager\MemberAccessTokenManagerInterface;
use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;
use App\Logic\Membership\MemberAccess\Service\MemberAccessPasswordHasher;
use App\Logic\Membership\MemberAccess\UseCase\ResolveMemberAccessSessionUseCase;
use App\Logic\Membership\MemberMessage\Manager\MemberMessageManagerInterface;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;
use App\Logic\Membership\MemberMessage\UseCase\SendMemberMessageUseCase;
use App\Logic\Membership\PaymentInterval;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Service\ConfiguredMailTransportFactory;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
use App\Logic\Settings\MailTemplate\Service\MailContentRenderer;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SendMemberMessageUseCaseTest extends TestCase
{
    private const string PASSWORD = 'aB3!xy9?';

    public function testSavesTheMessageForAMemberReachableThroughTheToken(): void
    {
        $resolveSession = new ResolveMemberAccessSessionUseCase(
            $this->tokenManager(),
            $this->memberManager(),
            $this->createStub(ContributionRateManagerInterface::class),
            $this->contributionRateSettingsManager(),
            new MemberAccessPasswordHasher(),
            $this->clock(),
        );

        $messages = $this->createMock(MemberMessageManagerInterface::class);
        $messages->expects(self::once())->method('save')->willReturnCallback(
            static function (MemberMessage $message): MemberMessage {
                self::assertSame('member-1', $message->memberId);
                self::assertSame('Bad-001', $message->memberNumber);
                self::assertSame('Erika Musterfrau', $message->memberName);
                self::assertSame('Meine Adresse hat sich geändert.', $message->message);

                return $message;
            },
        );

        $useCase = new SendMemberMessageUseCase(
            $resolveSession,
            $messages,
            $this->identifierGenerator(),
            $this->clock(),
            $this->notConfiguredMailer(),
        );

        $response = $useCase->execute('raw-token', self::PASSWORD, 'member-1', 'Meine Adresse hat sich geändert.');

        self::assertSame('member-1', $response->memberId);
    }

    /**
     * Der Token bestätigt nur den Zugriff auf die E-Mail-Adresse, nicht automatisch auf jedes
     * einzelne Mitglied dahinter — eine fremde `memberId` wird abgelehnt.
     */
    public function testRejectsAMemberIdNotReachableThroughTheToken(): void
    {
        $resolveSession = new ResolveMemberAccessSessionUseCase(
            $this->tokenManager(),
            $this->memberManager(),
            $this->createStub(ContributionRateManagerInterface::class),
            $this->contributionRateSettingsManager(),
            new MemberAccessPasswordHasher(),
            $this->clock(),
        );

        $messages = $this->createMock(MemberMessageManagerInterface::class);
        $messages->expects(self::never())->method('save');

        $useCase = new SendMemberMessageUseCase(
            $resolveSession,
            $messages,
            $this->identifierGenerator(),
            $this->clock(),
            $this->notConfiguredMailer(),
        );

        $this->expectException(InvalidMemberAccessTokenException::class);
        $useCase->execute('raw-token', self::PASSWORD, 'someone-elses-member-id', 'Text');
    }

    private function tokenManager(): MemberAccessTokenManagerInterface
    {
        $tokens = $this->createStub(MemberAccessTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn(new MemberAccessToken(
            'token-1',
            'erika@example.test',
            hash('sha256', 'raw-token'),
            new \DateTimeImmutable('2026-06-01T10:05:00+02:00'),
            'Bad-001',
            password_hash(self::PASSWORD, PASSWORD_DEFAULT),
        ));

        return $tokens;
    }

    private function memberManager(): MemberManagerInterface
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByPrimaryMemberNumber')->willReturn([new Member(
            id: 'member-1',
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
            contributionCategory: null,
            contributionAmountCents: null,
            workAssignmentSurchargeCents: null,
            remarks: [],
            oneTimeCharges: [],
            version: 0,
        )]);

        return $members;
    }

    private function contributionRateSettingsManager(): ContributionRateSettingsManagerInterface
    {
        $settings = $this->createStub(ContributionRateSettingsManagerInterface::class);
        $settings->method('get')->willReturn(new ContributionRateSettings(null));

        return $settings;
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01T10:00:00+02:00'));

        return $clock;
    }

    private function identifierGenerator(): IdentifierGeneratorInterface
    {
        $generator = $this->createStub(IdentifierGeneratorInterface::class);
        $generator->method('generate')->willReturn('message-1');

        return $generator;
    }

    private function notConfiguredMailer(): NotificationMailer
    {
        $emailSettingsManager = $this->createStub(EmailSettingsManagerInterface::class);
        $emailSettingsManager->method('get')->willReturn(new EmailSettings(null, null, null, null, null, null, null, []));

        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturnCallback(
            static fn (MailTemplateKey $key): MailTemplate => new MailTemplate($key, $key->defaultSubject(), $key->defaultBody()),
        );
        $renderer = new MailTemplateRenderer($manager, new MailContentRenderer(), $this->createStub(MailSignatureManagerInterface::class));

        return new NotificationMailer(
            $emailSettingsManager,
            $this->createStub(ConfiguredMailTransportFactory::class),
            $renderer,
            new BrandedEmailLayout(),
            $this->createStub(EmailLogoProviderInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }
}
