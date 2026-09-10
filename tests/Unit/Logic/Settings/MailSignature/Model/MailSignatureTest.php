<?php

namespace App\Tests\Unit\Logic\Settings\MailSignature\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\MailSignature\Model\MailSignature;
use PHPUnit\Framework\TestCase;

final class MailSignatureTest extends TestCase
{
    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new MailSignature('id-1', '   ', 'Text');
    }

    public function testRejectsAnEmptyBody(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        new MailSignature('id-1', 'Name', '   ');
    }

    public function testWithTextReplacesNameAndBodyButKeepsTheId(): void
    {
        $signature = new MailSignature('id-1', 'Alt', 'Alter Text');

        $updated = $signature->withText('Neu', 'Neuer Text');

        self::assertSame('id-1', $updated->id);
        self::assertSame('Neu', $updated->name);
        self::assertSame('Neuer Text', $updated->body);
    }
}
