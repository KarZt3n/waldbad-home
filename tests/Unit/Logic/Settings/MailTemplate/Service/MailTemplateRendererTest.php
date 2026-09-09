<?php

namespace App\Tests\Unit\Logic\Settings\MailTemplate\Service;

use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Service\MailTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class MailTemplateRendererTest extends TestCase
{
    public function testReplacesPlaceholdersInSubjectAndBody(): void
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(
            MailTemplateKey::MembershipApplicationApproved,
            'Willkommen {{vorname}}',
            'Hallo {{vorname}} {{nachname}}, Beitrag: {{beitraege}}',
        ));

        $rendered = (new MailTemplateRenderer($manager))->render(MailTemplateKey::MembershipApplicationApproved, [
            'vorname' => 'Erika',
            'nachname' => 'Musterfrau',
            'beitraege' => '50,00 €',
        ]);

        self::assertSame('Willkommen Erika', $rendered['subject']);
        self::assertSame('Hallo Erika Musterfrau, Beitrag: 50,00 €', $rendered['body']);
    }

    public function testLeavesUnknownPlaceholdersUntouched(): void
    {
        $manager = $this->createStub(MailTemplateManagerInterface::class);
        $manager->method('resolve')->willReturn(new MailTemplate(
            MailTemplateKey::MembershipApplicationApproved,
            'Betreff',
            'Hallo {{vorname}} {{tippfehler}}',
        ));

        $rendered = (new MailTemplateRenderer($manager))->render(MailTemplateKey::MembershipApplicationApproved, ['vorname' => 'Erika']);

        self::assertSame('Hallo Erika {{tippfehler}}', $rendered['body']);
    }
}
