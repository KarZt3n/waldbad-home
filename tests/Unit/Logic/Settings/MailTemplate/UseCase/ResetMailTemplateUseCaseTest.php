<?php

namespace App\Tests\Unit\Logic\Settings\MailTemplate\UseCase;

use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\UseCase\ResetMailTemplateUseCase;
use PHPUnit\Framework\TestCase;

final class ResetMailTemplateUseCaseTest extends TestCase
{
    public function testRestoresTheDefaultText(): void
    {
        $key = MailTemplateKey::MembershipApplicationApproved;
        $manager = $this->createMock(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate($key, 'angepasster Betreff', 'angepasster Text', 'signature-1'));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (MailTemplate $template) use ($key): MailTemplate {
                self::assertSame($key->defaultSubject(), $template->subject);
                self::assertSame($key->defaultBody(), $template->body);
                self::assertNull($template->signatureId);

                return $template;
            },
        );

        (new ResetMailTemplateUseCase($manager))->execute($key);
    }
}
