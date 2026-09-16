<?php

namespace App\Tests\Unit\Logic\IdentityAccess\LoginToken\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Common\SecureTokenGeneratorInterface;
use App\Logic\IdentityAccess\LoginToken\LoginLinkBuilderInterface;
use App\Logic\IdentityAccess\LoginToken\Manager\LoginTokenManagerInterface;
use App\Logic\IdentityAccess\LoginToken\Model\LoginToken;
use App\Logic\IdentityAccess\LoginToken\UseCase\RequestLoginUseCase;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\User;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\EmailSettings;
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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class RequestLoginUseCaseTest extends TestCase
{
    public function testDoesNothingForAnInvalidEmailFormat(): void
    {
        $users = $this->createMock(UserManagerInterface::class);
        $users->expects(self::never())->method('findByEmail');

        (new RequestLoginUseCase(
            $users,
            $this->createStub(LoginTokenManagerInterface::class),
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->noopMailer(),
            $this->createStub(LoginLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
        ))->execute('not-an-email');
    }

    public function testDoesNothingWhenNoUserUsesThatEmail(): void
    {
        $users = $this->createStub(UserManagerInterface::class);
        $users->method('findByEmail')->willReturn(null);
        $tokens = $this->createMock(LoginTokenManagerInterface::class);
        $tokens->expects(self::never())->method('save');

        (new RequestLoginUseCase(
            $users,
            $tokens,
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->noopMailer(),
            $this->createStub(LoginLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
        ))->execute('unknown@example.test');
    }

    /**
     * Ein gesperrter Benutzer erhält keinen Anmeldelink — dieselbe Antwort wie bei einer unbekannten
     * E-Mail-Adresse liefert der aufrufende Controller ohnehin immer (siehe `AuthenticationController`).
     */
    public function testDoesNothingWhenTheUserIsSuspended(): void
    {
        $users = $this->createStub(UserManagerInterface::class);
        $users->method('findByEmail')->willReturn($this->user(active: false));
        $tokens = $this->createMock(LoginTokenManagerInterface::class);
        $tokens->expects(self::never())->method('save');

        (new RequestLoginUseCase(
            $users,
            $tokens,
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->noopMailer(),
            $this->createStub(LoginLinkBuilderInterface::class),
            $this->createStub(LoggerInterface::class),
        ))->execute('erika@example.test');
    }

    public function testSavesAHashedTokenAndSendsTheLinkForAnActiveUser(): void
    {
        $users = $this->createStub(UserManagerInterface::class);
        $users->method('findByEmail')->willReturn($this->user());

        $tokenGenerator = $this->createStub(SecureTokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('raw-token-value');

        $now = new \DateTimeImmutable('2026-06-01T10:00:00+02:00');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $savedToken = null;
        $tokens = $this->createMock(LoginTokenManagerInterface::class);
        $tokens->expects(self::once())->method('save')->willReturnCallback(
            function (LoginToken $token) use (&$savedToken): LoginToken {
                $savedToken = $token;

                return $token;
            },
        );

        $linkBuilder = $this->createStub(LoginLinkBuilderInterface::class);
        $linkBuilder->method('build')->willReturn('https://example.test/admin?login_token=raw-token-value');

        $capturedEmail = null;
        $mailer = $this->configuredMailer($capturedEmail);

        (new RequestLoginUseCase(
            $users,
            $tokens,
            $tokenGenerator,
            $this->identifierGenerator('token-id-1'),
            $clock,
            $mailer,
            $linkBuilder,
            $this->createStub(LoggerInterface::class),
        ))->execute('  Erika@Example.test  ');

        self::assertNotNull($savedToken);
        self::assertSame('erika@example.test', $savedToken->email);
        self::assertSame(hash('sha256', 'raw-token-value'), $savedToken->tokenHash);
        self::assertEquals($now->modify('+30 minutes'), $savedToken->expiresAt);
        self::assertFalse($savedToken->isConsumed());

        self::assertNotNull($capturedEmail);
        self::assertStringContainsString('https://example.test/admin?login_token=raw-token-value', (string) $capturedEmail->getTextBody());
        self::assertStringContainsString('30 Minuten', (string) $capturedEmail->getTextBody());
    }

    public function testLogsInsteadOfThrowingWhenSomethingFails(): void
    {
        $users = $this->createStub(UserManagerInterface::class);
        $users->method('findByEmail')->willThrowException(new \RuntimeException('DB down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        (new RequestLoginUseCase(
            $users,
            $this->createStub(LoginTokenManagerInterface::class),
            $this->createStub(SecureTokenGeneratorInterface::class),
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            $this->noopMailer(),
            $this->createStub(LoginLinkBuilderInterface::class),
            $logger,
        ))->execute('erika@example.test');
    }

    private function user(bool $active = true): User
    {
        return new User(
            id: 'user-1',
            email: 'erika@example.test',
            displayName: 'Erika Musterfrau',
            roles: [],
            moduleAccess: [new ModuleAccess(CmsModule::Pages, ModuleRole::Viewer)],
            active: $active,
            version: 1,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            updatedAt: new \DateTimeImmutable('2026-01-01'),
            lastLoginAt: null,
        );
    }

    private function identifierGenerator(string $id): IdentifierGeneratorInterface
    {
        $generator = $this->createStub(IdentifierGeneratorInterface::class);
        $generator->method('generate')->willReturn($id);

        return $generator;
    }

    private function noopMailer(): NotificationMailer
    {
        $emailSettingsManager = $this->createStub(EmailSettingsManagerInterface::class);
        $emailSettingsManager->method('get')->willReturn(new EmailSettings([]));

        return new NotificationMailer(
            $this->createStub(MailerInterface::class),
            $emailSettingsManager,
            $this->realRenderer(),
            new BrandedEmailLayout(),
            $this->createStub(EmailLogoProviderInterface::class),
            $this->createStub(LoggerInterface::class),
            'from@example.test',
            'Verein',
        );
    }

    private function configuredMailer(?Email &$capturedEmail): NotificationMailer
    {
        $emailSettingsManager = $this->createStub(EmailSettingsManagerInterface::class);
        $emailSettingsManager->method('get')->willReturn(new EmailSettings([]));

        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (Email $email) use (&$capturedEmail): void {
            $capturedEmail = $email;
        });

        return new NotificationMailer(
            $mailer,
            $emailSettingsManager,
            $this->realRenderer(),
            new BrandedEmailLayout(),
            $this->createStub(EmailLogoProviderInterface::class),
            $this->createStub(LoggerInterface::class),
            'from@example.test',
            'Verein',
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
