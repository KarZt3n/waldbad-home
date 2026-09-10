<?php

namespace App\Tests\Unit\Logic\Settings\MailSignature\Query;

use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailSignature\Model\MailSignature;
use App\Logic\Settings\MailSignature\Query\ListMailSignaturesQuery;
use PHPUnit\Framework\TestCase;

final class ListMailSignaturesQueryTest extends TestCase
{
    public function testMapsEverySignatureToAResponse(): void
    {
        $manager = $this->createStub(MailSignatureManagerInterface::class);
        $manager->method('list')->willReturn([
            new MailSignature('id-1', 'Standard', 'Freundliche Grüße'),
            new MailSignature('id-2', 'Kurz', 'Grüße'),
        ]);

        $responses = (new ListMailSignaturesQuery($manager))->execute();

        self::assertCount(2, $responses);
        self::assertSame('id-1', $responses[0]->id);
        self::assertSame('Standard', $responses[0]->name);
        self::assertSame('id-2', $responses[1]->id);
    }
}
