<?php

namespace App\Tests\Unit\Logic\Settings\MailTemplate\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\UseCase\UpdateMailTemplateUseCase;
use PHPUnit\Framework\TestCase;

final class UpdateMailTemplateUseCaseTest extends TestCase
{
    public function testStoresTheTrimmedText(): void
    {
        $manager = $this->createMock(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(MailTemplateKey::MembershipApplicationApproved, 'alt', 'alt'));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (MailTemplate $template): MailTemplate {
                self::assertSame('Neuer Betreff', $template->subject);
                self::assertSame('Neuer Text', $template->body);

                return $template;
            },
        );

        (new UpdateMailTemplateUseCase($manager))->execute(MailTemplateKey::MembershipApplicationApproved, '  Neuer Betreff  ', '  Neuer Text  ');
    }

    public function testRejectsAnEmptySubjectOrBody(): void
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(MailTemplateKey::MembershipApplicationApproved, 'alt', 'alt'));

        $this->expectException(BusinessRuleViolationException::class);

        (new UpdateMailTemplateUseCase($manager))->execute(MailTemplateKey::MembershipApplicationApproved, '   ', 'Text');
    }
}
