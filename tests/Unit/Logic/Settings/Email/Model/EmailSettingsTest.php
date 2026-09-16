<?php

namespace App\Tests\Unit\Logic\Settings\Email\Model;

use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Model\NotificationEvent;
use PHPUnit\Framework\TestCase;

final class EmailSettingsTest extends TestCase
{
    public function testRecipientsForReturnsEmptyListWithoutEntry(): void
    {
        $settings = new EmailSettings([]);

        self::assertSame([], $settings->recipientsFor(NotificationEvent::MembershipApplicationSubmitted));
    }

    public function testWithRecipientsForSetsAndClearsAnEntry(): void
    {
        $settings = new EmailSettings([]);

        $withRecipients = $settings->withRecipientsFor(NotificationEvent::MembershipApplicationSubmitted, ['a@example.test', 'b@example.test']);
        self::assertSame(['a@example.test', 'b@example.test'], $withRecipients->recipientsFor(NotificationEvent::MembershipApplicationSubmitted));

        $cleared = $withRecipients->withRecipientsFor(NotificationEvent::MembershipApplicationSubmitted, []);
        self::assertSame([], $cleared->recipientsFor(NotificationEvent::MembershipApplicationSubmitted));
        self::assertArrayNotHasKey(NotificationEvent::MembershipApplicationSubmitted->value, $cleared->notificationRecipients);
    }
}
