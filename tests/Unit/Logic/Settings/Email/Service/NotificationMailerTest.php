<?php

namespace App\Tests\Unit\Logic\Settings\Email\Service;

use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Model\MailDelivery;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\EmailLogo;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Service\BrandedEmailLayout;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;
use App\Logic\Settings\MailTemplate\Service\MailContentRenderer;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class NotificationMailerTest extends TestCase
{
    public function testNotifyDoesNothingWithoutRecipientsForTheEvent(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings([]));
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->mailerFor($manager, $mailer)
            ->notify(NotificationEvent::MembershipApplicationSubmitted, MailTemplateKey::MembershipApplicationSubmittedNotification, []);
    }

    public function testNotifySendsTheRenderedTemplateToEveryConfiguredRecipient(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings([
            NotificationEvent::MembershipApplicationSubmitted->value => ['a@example.test', 'b@example.test'],
        ]));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(
            static fn (Email $email): bool => $email->getHeaders()->get('X-Transport')?->getBodyAsString() === 'main',
        ));

        $this->mailerFor($manager, $mailer, $this->renderer('Hallo {{vorname}}'))
            ->notify(NotificationEvent::MembershipApplicationSubmitted, MailTemplateKey::MembershipApplicationSubmittedNotification, ['vorname' => 'Erika']);
    }

    public function testNotifySwallowsATransportFailureInsteadOfThrowing(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings([
            NotificationEvent::MembershipApplicationSubmitted->value => ['a@example.test'],
        ]));

        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('Connection refused'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $this->mailerFor($manager, $mailer, $this->renderer(), $logger)
            ->notify(NotificationEvent::MembershipApplicationSubmitted, MailTemplateKey::MembershipApplicationSubmittedNotification, []);
    }

    public function testSendToSendsToExactlyThatRecipient(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings([]));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(
            static fn (Email $email): bool => count($email->getTo()) === 1
                && $email->getTo()[0]->getAddress() === 'applicant@example.test'
                && $email->getFrom()[0]->getAddress() === 'from@example.test'
                && $email->getReplyTo()[0]->getAddress() === 'from@example.test'
                && $email->getHeaders()->get('X-Transport')?->getBodyAsString() === 'main',
        ));

        $this->mailerFor($manager, $mailer)
            ->sendTo('applicant@example.test', MailTemplateKey::MembershipApplicationApproved, ['vorname' => 'Erika']);
    }

    public function testSendToRequestsTheExpressTransportForTimeCriticalMails(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings([]));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(
            static fn (Email $email): bool => $email->getHeaders()->get('X-Transport')?->getBodyAsString() === 'express',
        ));

        $this->mailerFor($manager, $mailer)
            ->sendTo('admin@example.test', MailTemplateKey::AdminLoginMagicLink, ['link' => 'https://example.test'], MailDelivery::Express);
    }

    /** Gmail und andere Webmailer entfernen `data:`-Bilder; das Logo muss als eingebettetes Bild (CID) mitgehen. */
    public function testEmbedsTheLogoAsInlineImageReferencedByContentId(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings([]));
        $logo = $this->createStub(EmailLogoProviderInterface::class);
        $logo->method('getLogo')->willReturn(new EmailLogo('PNG-DATA', 'image/png'));
        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(static function (Email $email) use (&$sent): void {
            $sent = $email;
        });

        $this->mailerFor($manager, $mailer, logoProvider: $logo)
            ->sendTo('applicant@example.test', MailTemplateKey::MembershipApplicationApproved, ['vorname' => 'Erika']);

        self::assertInstanceOf(Email::class, $sent);
        $html = (string) $sent->getHtmlBody();
        self::assertStringContainsString('src="cid:logo"', $html);
        self::assertStringNotContainsString('data:', $html);
        $attachments = $sent->getAttachments();
        self::assertCount(1, $attachments);
        self::assertSame('image/png', $attachments[0]->getMediaType().'/'.$attachments[0]->getMediaSubtype());
        self::assertStringContainsString('cid:'.$attachments[0]->getContentId(), quoted_printable_decode($sent->toString()));
    }

    private function mailerFor(
        EmailSettingsManagerInterface $manager,
        MailerInterface $mailer,
        ?MailTemplateRenderer $renderer = null,
        ?LoggerInterface $logger = null,
        ?EmailLogoProviderInterface $logoProvider = null,
    ): NotificationMailer {
        return new NotificationMailer(
            $mailer,
            $manager,
            $renderer ?? $this->renderer(),
            new BrandedEmailLayout(),
            $logoProvider ?? $this->logoProvider(),
            $logger ?? new NullLogger(),
            'from@example.test',
            'Verein',
        );
    }

    private function renderer(string $body = 'Text'): MailTemplateRenderer
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturnCallback(
            static fn (MailTemplateKey $key): MailTemplate => new MailTemplate($key, 'Betreff', $body),
        );

        $signatures = $this->createStub(MailSignatureManagerInterface::class);
        $signatures->method('find')->willReturn(null);

        return new MailTemplateRenderer($manager, new MailContentRenderer(), $signatures);
    }

    /**
     * Kein Logo hinterlegt (entspricht z. B. einer minimalen Testumgebung ohne die Logo-Datei) —
     * die Mail wird dann ohne Logo im Kopfbereich zusammengebaut, siehe `BrandedEmailLayout`.
     */
    private function logoProvider(): EmailLogoProviderInterface
    {
        $provider = $this->createStub(EmailLogoProviderInterface::class);
        $provider->method('getLogo')->willReturn(null);

        return $provider;
    }
}
