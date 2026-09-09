<?php

namespace App\Tests\Unit\Logic\Settings\Email\Model;

use App\Logic\Settings\Email\Model\EmailProviderPreset;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Model\NotificationEvent;
use PHPUnit\Framework\TestCase;

final class EmailSettingsTest extends TestCase
{
    public function testIsConfiguredRequiresHostAndFromAddress(): void
    {
        self::assertFalse((new EmailSettings(null, null, null, null, null, null, null, []))->isConfigured());
        self::assertFalse((new EmailSettings(null, 'smtp.example.test', null, null, null, null, null, []))->isConfigured());
        self::assertFalse((new EmailSettings(null, null, null, null, null, 'from@example.test', null, []))->isConfigured());
        self::assertTrue((new EmailSettings(null, 'smtp.example.test', 587, null, null, 'from@example.test', null, []))->isConfigured());
    }

    public function testRecipientsForReturnsEmptyListWithoutEntry(): void
    {
        $settings = new EmailSettings(null, null, null, null, null, null, null, []);

        self::assertSame([], $settings->recipientsFor(NotificationEvent::MembershipApplicationSubmitted));
    }

    public function testWithConnectionReplacesConnectionFieldsButKeepsRecipients(): void
    {
        $settings = new EmailSettings(null, null, null, null, null, null, null, [
            NotificationEvent::MembershipApplicationSubmitted->value => ['a@example.test'],
        ]);

        $updated = $settings->withConnection(EmailProviderPreset::Google, 'smtp.gmail.com', 587, 'user', 'pw', 'from@example.test', 'Verein');

        self::assertSame(EmailProviderPreset::Google, $updated->provider);
        self::assertSame('smtp.gmail.com', $updated->host);
        self::assertSame(587, $updated->port);
        self::assertSame('user', $updated->username);
        self::assertSame('pw', $updated->password);
        self::assertSame('from@example.test', $updated->fromAddress);
        self::assertSame('Verein', $updated->fromName);
        self::assertSame(['a@example.test'], $updated->recipientsFor(NotificationEvent::MembershipApplicationSubmitted));
    }

    public function testWithRecipientsForSetsAndClearsAnEntry(): void
    {
        $settings = new EmailSettings(null, null, null, null, null, null, null, []);

        $withRecipients = $settings->withRecipientsFor(NotificationEvent::MembershipApplicationSubmitted, ['a@example.test', 'b@example.test']);
        self::assertSame(['a@example.test', 'b@example.test'], $withRecipients->recipientsFor(NotificationEvent::MembershipApplicationSubmitted));

        $cleared = $withRecipients->withRecipientsFor(NotificationEvent::MembershipApplicationSubmitted, []);
        self::assertSame([], $cleared->recipientsFor(NotificationEvent::MembershipApplicationSubmitted));
        self::assertArrayNotHasKey(NotificationEvent::MembershipApplicationSubmitted->value, $cleared->notificationRecipients);
    }
}
