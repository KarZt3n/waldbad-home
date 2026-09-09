<?php

namespace App\Tests\Unit\Logic\Settings\Email\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Email\Dto\UpdateEmailSettingsRequest;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\EmailProviderPreset;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\UseCase\UpdateEmailSettingsUseCase;
use PHPUnit\Framework\TestCase;

final class UpdateEmailSettingsUseCaseTest extends TestCase
{
    public function testStoresTheConnectionDetails(): void
    {
        $manager = $this->createMock(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings(null, null, null, null, null, null, null, []));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (EmailSettings $settings): EmailSettings {
                self::assertSame(EmailProviderPreset::Google, $settings->provider);
                self::assertSame('smtp.gmail.com', $settings->host);
                self::assertSame(587, $settings->port);
                self::assertSame('verein@example.test', $settings->username);
                self::assertSame('app-password', $settings->password);
                self::assertSame('verein@example.test', $settings->fromAddress);
                self::assertSame('Waldbad Borkheide', $settings->fromName);

                return $settings;
            },
        );

        (new UpdateEmailSettingsUseCase($manager))->execute(new UpdateEmailSettingsRequest(
            provider: EmailProviderPreset::Google,
            host: 'smtp.gmail.com',
            port: 587,
            username: 'verein@example.test',
            password: 'app-password',
            fromAddress: 'verein@example.test',
            fromName: 'Waldbad Borkheide',
        ));
    }

    public function testKeepsTheExistingPasswordWhenNoneIsSubmitted(): void
    {
        $manager = $this->createMock(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings(null, 'smtp.example.test', 587, 'user', 'old-password', 'from@example.test', null, []));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (EmailSettings $settings): EmailSettings {
                self::assertSame('old-password', $settings->password);

                return $settings;
            },
        );

        (new UpdateEmailSettingsUseCase($manager))->execute(new UpdateEmailSettingsRequest(
            provider: null,
            host: 'smtp.example.test',
            port: 587,
            username: 'user',
            password: null,
            fromAddress: 'from@example.test',
            fromName: null,
        ));
    }

    public function testRejectsAnInvalidFromAddress(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings(null, null, null, null, null, null, null, []));

        $this->expectException(BusinessRuleViolationException::class);

        (new UpdateEmailSettingsUseCase($manager))->execute(new UpdateEmailSettingsRequest(
            provider: null, host: 'smtp.example.test', port: null, username: null, password: null,
            fromAddress: 'not-an-email', fromName: null,
        ));
    }

    public function testRejectsAnOutOfRangePort(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings(null, null, null, null, null, null, null, []));

        $this->expectException(BusinessRuleViolationException::class);

        (new UpdateEmailSettingsUseCase($manager))->execute(new UpdateEmailSettingsRequest(
            provider: null, host: 'smtp.example.test', port: 70000, username: null, password: null,
            fromAddress: 'from@example.test', fromName: null,
        ));
    }
}
