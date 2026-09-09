<?php

namespace App\Tests\Unit\Logic\Settings\Email\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Service\ConfiguredMailTransportFactory;
use App\Logic\Settings\Email\UseCase\SendTestEmailUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class SendTestEmailUseCaseTest extends TestCase
{
    public function testSendsAMessageThroughTheConfiguredTransport(): void
    {
        $settings = new EmailSettings(null, 'smtp.example.test', 587, 'user', 'pw', 'from@example.test', 'Verein', []);
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn($settings);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('send');
        $factory = $this->createMock(ConfiguredMailTransportFactory::class);
        $factory->expects(self::once())->method('create')->with($settings)->willReturn($transport);

        (new SendTestEmailUseCase($manager, $factory))->execute('to@example.test');
    }

    public function testRejectsAnInvalidRecipientAddress(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $factory = $this->createStub(ConfiguredMailTransportFactory::class);

        $this->expectException(BusinessRuleViolationException::class);

        (new SendTestEmailUseCase($manager, $factory))->execute('not-an-email');
    }

    public function testWrapsATransportFailureAsABusinessRuleViolation(): void
    {
        $settings = new EmailSettings(null, 'smtp.example.test', 587, 'user', 'pw', 'from@example.test', null, []);
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn($settings);

        $transport = $this->createStub(TransportInterface::class);
        $transport->method('send')->willThrowException(new TransportException('Connection refused'));
        $factory = $this->createStub(ConfiguredMailTransportFactory::class);
        $factory->method('create')->willReturn($transport);

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessageMatches('/Connection refused/');

        (new SendTestEmailUseCase($manager, $factory))->execute('to@example.test');
    }
}
