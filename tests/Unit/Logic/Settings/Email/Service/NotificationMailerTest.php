<?php

namespace App\Tests\Unit\Logic\Settings\Email\Service;

use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\Service\ConfiguredMailTransportFactory;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class NotificationMailerTest extends TestCase
{
    public function testNotifyDoesNothingWhenNotConfigured(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings(null, null, null, null, null, null, null, [
            NotificationEvent::MembershipApplicationSubmitted->value => ['a@example.test'],
        ]));
        $factory = $this->createMock(ConfiguredMailTransportFactory::class);
        $factory->expects(self::never())->method('create');

        (new NotificationMailer($manager, $factory, $this->renderer(), new NullLogger()))
            ->notify(NotificationEvent::MembershipApplicationSubmitted, MailTemplateKey::MembershipApplicationSubmittedNotification, []);
    }

    public function testNotifyDoesNothingWithoutRecipientsForTheEvent(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings(null, 'smtp.example.test', 587, null, null, 'from@example.test', null, []));
        $factory = $this->createMock(ConfiguredMailTransportFactory::class);
        $factory->expects(self::never())->method('create');

        (new NotificationMailer($manager, $factory, $this->renderer(), new NullLogger()))
            ->notify(NotificationEvent::MembershipApplicationSubmitted, MailTemplateKey::MembershipApplicationSubmittedNotification, []);
    }

    public function testNotifySendsTheRenderedTemplateToEveryConfiguredRecipient(): void
    {
        $settings = new EmailSettings(null, 'smtp.example.test', 587, null, null, 'from@example.test', 'Verein', [
            NotificationEvent::MembershipApplicationSubmitted->value => ['a@example.test', 'b@example.test'],
        ]);
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn($settings);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('send');
        $factory = $this->createStub(ConfiguredMailTransportFactory::class);
        $factory->method('create')->willReturn($transport);

        (new NotificationMailer($manager, $factory, $this->renderer('Hallo {{vorname}}'), new NullLogger()))
            ->notify(NotificationEvent::MembershipApplicationSubmitted, MailTemplateKey::MembershipApplicationSubmittedNotification, ['vorname' => 'Erika']);
    }

    public function testNotifySwallowsATransportFailureInsteadOfThrowing(): void
    {
        $settings = new EmailSettings(null, 'smtp.example.test', 587, null, null, 'from@example.test', null, [
            NotificationEvent::MembershipApplicationSubmitted->value => ['a@example.test'],
        ]);
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn($settings);

        $transport = $this->createStub(TransportInterface::class);
        $transport->method('send')->willThrowException(new TransportException('Connection refused'));
        $factory = $this->createStub(ConfiguredMailTransportFactory::class);
        $factory->method('create')->willReturn($transport);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        (new NotificationMailer($manager, $factory, $this->renderer(), $logger))
            ->notify(NotificationEvent::MembershipApplicationSubmitted, MailTemplateKey::MembershipApplicationSubmittedNotification, []);
    }

    public function testSendToDoesNothingWhenNotConfigured(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings(null, null, null, null, null, null, null, []));
        $factory = $this->createMock(ConfiguredMailTransportFactory::class);
        $factory->expects(self::never())->method('create');

        (new NotificationMailer($manager, $factory, $this->renderer(), new NullLogger()))
            ->sendTo('applicant@example.test', MailTemplateKey::MembershipApplicationApproved, []);
    }

    public function testSendToSendsToExactlyThatRecipient(): void
    {
        $settings = new EmailSettings(null, 'smtp.example.test', 587, null, null, 'from@example.test', 'Verein', []);
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn($settings);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('send');
        $factory = $this->createStub(ConfiguredMailTransportFactory::class);
        $factory->method('create')->willReturn($transport);

        (new NotificationMailer($manager, $factory, $this->renderer(), new NullLogger()))
            ->sendTo('applicant@example.test', MailTemplateKey::MembershipApplicationApproved, ['vorname' => 'Erika']);
    }

    private function renderer(string $body = 'Text'): MailTemplateRenderer
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturnCallback(
            static fn (MailTemplateKey $key): MailTemplate => new MailTemplate($key, 'Betreff', $body),
        );

        return new MailTemplateRenderer($manager);
    }
}
