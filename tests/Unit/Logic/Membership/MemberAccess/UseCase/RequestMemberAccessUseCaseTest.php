<?php

namespace App\Tests\Unit\Logic\Membership\MemberAccess\UseCase;

use App\Logic\Common\AccessPasswordGeneratorInterface;
use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Common\SecureTokenGeneratorInterface;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\MemberAccess\MemberAccessLinkBuilderInterface;
use App\Logic\Membership\MemberAccess\Manager\MemberAccessTokenManagerInterface;
use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;
use App\Logic\Membership\MemberAccess\Service\MemberAccessPasswordHasher;
use App\Logic\Membership\MemberAccess\UseCase\RequestMemberAccessUseCase;
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
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

final class RequestMemberAccessUseCaseTest extends TestCase
{
    private const string PASSWORD = 'aB3!xy9?';

    public function testDoesNothingWhenNoMemberUsesThatEmail(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByEmail')->willReturn([]);
        $tokens = $this->createMock(MemberAccessTokenManagerInterface::class);
        $tokens->expects(self::never())->method('save');

        (new RequestMemberAccessUseCase(
            $members,
            $tokens,
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->notConfiguredMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
        ))->execute('unknown@example.test', '1990-01-01');
    }

    public function testDoesNothingForAnInvalidEmailFormat(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->expects(self::never())->method('findByEmail');

        (new RequestMemberAccessUseCase(
            $members,
            $this->createStub(MemberAccessTokenManagerInterface::class),
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->notConfiguredMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
        ))->execute('not-an-email', '1990-01-01');
    }

    public function testDoesNothingForAnUnparseableBirthDate(): void
    {
        $members = $this->createMock(MemberManagerInterface::class);
        $members->expects(self::never())->method('findByEmail');

        (new RequestMemberAccessUseCase(
            $members,
            $this->createStub(MemberAccessTokenManagerInterface::class),
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->notConfiguredMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
        ))->execute('erika@example.test', 'not-a-date');
    }

    /**
     * Die E-Mail-Adresse allein reicht nicht — sie identifiziert in der Regel einen ganzen
     * Haushalt, erst das zusätzlich passende Geburtsdatum grenzt auf die anfragende Person ein.
     */
    public function testDoesNothingWhenTheBirthDateDoesNotMatch(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByEmail')->willReturn([$this->member()]);
        $tokens = $this->createMock(MemberAccessTokenManagerInterface::class);
        $tokens->expects(self::never())->method('save');

        (new RequestMemberAccessUseCase(
            $members,
            $tokens,
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->notConfiguredMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
        ))->execute('erika@example.test', '1991-02-03');
    }

    /**
     * Kernstück: bei einer E-Mail-Adresse + Geburtsdatum, die zusammen zu einem Mitglied passen,
     * wird ein an dessen Haushalt (`primaryMemberNumber`) gebundener Token mit dem SHA-256-Hash des
     * (unveränderten) Klartext-Tokens gespeichert, der Ablauf liegt 30 Minuten in der Zukunft, und
     * die Mail enthält den vom `MemberAccessLinkBuilderInterface` gebauten Link.
     */
    public function testSavesAHashedTokenAndSendsTheLinkWhenAMemberUsesThatEmail(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByEmail')->willReturn([$this->member()]);

        $tokenGenerator = $this->createStub(SecureTokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('raw-token-value');

        $now = new \DateTimeImmutable('2026-06-01T10:00:00+02:00');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $savedToken = null;
        $tokens = $this->createMock(MemberAccessTokenManagerInterface::class);
        $tokens->expects(self::once())->method('save')->willReturnCallback(
            function (MemberAccessToken $token) use (&$savedToken): MemberAccessToken {
                $savedToken = $token;

                return $token;
            },
        );

        $linkBuilder = $this->createStub(MemberAccessLinkBuilderInterface::class);
        $linkBuilder->method('build')->willReturn('https://example.test/meine-mitgliedschaft?token=raw-token-value');

        $capturedEmail = null;
        $mailer = $this->configuredMailer($capturedEmail);

        $passwordHasher = new MemberAccessPasswordHasher();

        (new RequestMemberAccessUseCase(
            $members,
            $tokens,
            $tokenGenerator,
            $this->passwordGenerator(),
            $passwordHasher,
            $this->identifierGenerator('token-id-1'),
            $clock,
            $mailer,
            $linkBuilder,
            $this->createStub(LoggerInterface::class),
        ))->execute('  Erika@Example.test  ', '1990-01-01');

        self::assertNotNull($savedToken);
        self::assertSame('erika@example.test', $savedToken->email);
        self::assertSame(hash('sha256', 'raw-token-value'), $savedToken->tokenHash);
        self::assertSame('Bad-001', $savedToken->primaryMemberNumber);
        self::assertTrue($passwordHasher->verify(self::PASSWORD, $savedToken->passwordHash));
        self::assertEquals($now->modify('+30 minutes'), $savedToken->expiresAt);

        self::assertNotNull($capturedEmail);
        self::assertStringContainsString('https://example.test/meine-mitgliedschaft?token=raw-token-value', (string) $capturedEmail->getTextBody());
        self::assertStringContainsString(self::PASSWORD, (string) $capturedEmail->getTextBody());
        self::assertStringContainsString('30 Minuten', (string) $capturedEmail->getTextBody());
    }

    public function testLogsInsteadOfThrowingWhenSomethingFails(): void
    {
        $members = $this->createStub(MemberManagerInterface::class);
        $members->method('findByEmail')->willThrowException(new \RuntimeException('DB down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        (new RequestMemberAccessUseCase(
            $members,
            $this->createStub(MemberAccessTokenManagerInterface::class),
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->passwordGenerator(),
            new MemberAccessPasswordHasher(),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->notConfiguredMailer(),
            $this->createStub(MemberAccessLinkBuilderInterface::class),
            $logger,
        ))->execute('erika@example.test', '1990-01-01');
    }

    private function member(): Member
    {
        return new Member(
            id: 'member-1',
            memberNumber: 'Bad-001',
            primaryMemberNumber: 'Bad-001',
            salutation: \App\Logic\Membership\Member\Model\Salutation::Ms,
            lastName: 'Musterfrau',
            firstName: 'Erika',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            street: 'Kirchanger 14',
            postalCode: '14822',
            city: 'Borkheide',
            email: 'erika@example.test',
            phone: null,
            familyRole: \App\Logic\Membership\Member\Model\FamilyRole::None,
            joinedAt: new \DateTimeImmutable('2020-01-01'),
            leftAt: null,
            active: true,
            function: \App\Logic\Membership\Member\Model\MemberFunction::Member,
            accountHolder: null,
            iban: null,
            bankName: null,
            mandateReference: null,
            paymentMethod: \App\Logic\Membership\Member\Model\PaymentMethod::BankTransfer,
            paymentInterval: \App\Logic\Membership\PaymentInterval::Yearly,
            paymentDay: \App\Logic\Membership\Member\Model\PaymentDay::First,
            payerType: \App\Logic\Membership\Member\Model\PayerType::SelfPayer,
            payerMemberId: null,
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

    private function passwordGenerator(): AccessPasswordGeneratorInterface
    {
        $generator = $this->createStub(AccessPasswordGeneratorInterface::class);
        $generator->method('generate')->willReturn(self::PASSWORD);

        return $generator;
    }

    private function identifierGenerator(string $id): IdentifierGeneratorInterface
    {
        $generator = $this->createStub(IdentifierGeneratorInterface::class);
        $generator->method('generate')->willReturn($id);

        return $generator;
    }

    /**
     * Kein Mailserver konfiguriert: `NotificationMailer::sendTo()` wird dadurch zum No-op.
     */
    private function notConfiguredMailer(): NotificationMailer
    {
        $emailSettingsManager = $this->createStub(EmailSettingsManagerInterface::class);
        $emailSettingsManager->method('get')->willReturn(new EmailSettings(null, null, null, null, null, null, null, []));

        return new NotificationMailer(
            $emailSettingsManager,
            $this->createStub(ConfiguredMailTransportFactory::class),
            $this->realRenderer(),
            new BrandedEmailLayout(),
            $this->createStub(EmailLogoProviderInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    /**
     * Konfigurierter Mailserver mit einem Transport, der die gebaute Mail abfängt statt sie zu
     * verschicken — `$capturedEmail` wird per Referenz befüllt.
     */
    private function configuredMailer(?Email &$capturedEmail): NotificationMailer
    {
        $settings = new EmailSettings(null, 'smtp.example.test', 587, null, null, 'from@example.test', 'Verein', []);
        $emailSettingsManager = $this->createStub(EmailSettingsManagerInterface::class);
        $emailSettingsManager->method('get')->willReturn($settings);

        $transport = $this->createStub(TransportInterface::class);
        $transport->method('send')->willReturnCallback(function (Email $email) use (&$capturedEmail): void {
            $capturedEmail = $email;
        });
        $transportFactory = $this->createStub(ConfiguredMailTransportFactory::class);
        $transportFactory->method('create')->willReturn($transport);

        return new NotificationMailer(
            $emailSettingsManager,
            $transportFactory,
            $this->realRenderer(),
            new BrandedEmailLayout(),
            $this->createStub(EmailLogoProviderInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function realRenderer(): MailTemplateRenderer
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturnCallback(
            static fn (MailTemplateKey $key): MailTemplate => new MailTemplate($key, $key->defaultSubject(), $key->defaultBody()),
        );

        return new MailTemplateRenderer($manager, new MailContentRenderer(), $this->createStub(MailSignatureManagerInterface::class));
    }
}
