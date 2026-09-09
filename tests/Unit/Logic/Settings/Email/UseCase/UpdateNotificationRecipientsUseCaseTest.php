<?php

namespace App\Tests\Unit\Logic\Settings\Email\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\EmailSettings;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\UseCase\UpdateNotificationRecipientsUseCase;
use PHPUnit\Framework\TestCase;

final class UpdateNotificationRecipientsUseCaseTest extends TestCase
{
    public function testNormalizesTrimsLowercasesAndDeduplicatesRecipients(): void
    {
        $manager = $this->createMock(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings(null, null, null, null, null, null, null, []));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (EmailSettings $settings): EmailSettings {
                self::assertSame(
                    ['a@example.test', 'b@example.test'],
                    $settings->recipientsFor(NotificationEvent::MembershipApplicationSubmitted),
                );

                return $settings;
            },
        );

        (new UpdateNotificationRecipientsUseCase($manager))->execute(
            NotificationEvent::MembershipApplicationSubmitted,
            [' A@Example.test ', 'b@example.test', 'a@example.test', ''],
        );
    }

    public function testRejectsAnInvalidEmailAddress(): void
    {
        $manager = $this->createStub(EmailSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new EmailSettings(null, null, null, null, null, null, null, []));

        $this->expectException(BusinessRuleViolationException::class);

        (new UpdateNotificationRecipientsUseCase($manager))->execute(NotificationEvent::MembershipApplicationSubmitted, ['not-an-email']);
    }
}
