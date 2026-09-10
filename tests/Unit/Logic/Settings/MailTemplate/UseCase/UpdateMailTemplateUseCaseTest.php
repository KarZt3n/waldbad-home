<?php

namespace App\Tests\Unit\Logic\Settings\MailTemplate\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailSignature\Model\MailSignature;
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
                self::assertNull($template->signatureId);

                return $template;
            },
        );

        (new UpdateMailTemplateUseCase($manager, $this->noSignatures()))
            ->execute(MailTemplateKey::MembershipApplicationApproved, '  Neuer Betreff  ', '  Neuer Text  ', null);
    }

    public function testStoresTheAssignedSignature(): void
    {
        $manager = $this->createMock(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(MailTemplateKey::MembershipApplicationApproved, 'alt', 'alt'));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (MailTemplate $template): MailTemplate {
                self::assertSame('signature-1', $template->signatureId);

                return $template;
            },
        );
        $signatures = $this->createStub(MailSignatureManagerInterface::class);
        $signatures->method('find')->willReturn(new MailSignature('signature-1', 'Standard', 'Grüße'));

        (new UpdateMailTemplateUseCase($manager, $signatures))
            ->execute(MailTemplateKey::MembershipApplicationApproved, 'Betreff', 'Text', 'signature-1');
    }

    public function testRejectsAnUnknownSignature(): void
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(MailTemplateKey::MembershipApplicationApproved, 'alt', 'alt'));
        $signatures = $this->createStub(MailSignatureManagerInterface::class);
        $signatures->method('find')->willReturn(null);

        $this->expectException(BusinessRuleViolationException::class);

        (new UpdateMailTemplateUseCase($manager, $signatures))
            ->execute(MailTemplateKey::MembershipApplicationApproved, 'Betreff', 'Text', 'unknown');
    }

    public function testRejectsAnEmptySubjectOrBody(): void
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(MailTemplateKey::MembershipApplicationApproved, 'alt', 'alt'));

        $this->expectException(BusinessRuleViolationException::class);

        (new UpdateMailTemplateUseCase($manager, $this->noSignatures()))
            ->execute(MailTemplateKey::MembershipApplicationApproved, '   ', 'Text', null);
    }

    private function noSignatures(): MailSignatureManagerInterface
    {
        return $this->createStub(MailSignatureManagerInterface::class);
    }
}
