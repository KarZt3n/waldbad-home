<?php

namespace App\Tests\Unit\Logic\Settings\MailSignature\UseCase;

use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailSignature\Model\MailSignature;
use App\Logic\Settings\MailSignature\UseCase\UpdateMailSignatureUseCase;
use PHPUnit\Framework\TestCase;

final class UpdateMailSignatureUseCaseTest extends TestCase
{
    public function testUpdatesNameAndBodyButKeepsTheId(): void
    {
        $manager = $this->createMock(MailSignatureManagerInterface::class);
        $manager->method('get')->willReturn(new MailSignature('id-1', 'Alt', 'Alter Text'));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (MailSignature $signature): MailSignature {
                self::assertSame('id-1', $signature->id);
                self::assertSame('Neu', $signature->name);
                self::assertSame('Neuer Text', $signature->body);

                return $signature;
            },
        );

        (new UpdateMailSignatureUseCase($manager))->execute('id-1', 'Neu', 'Neuer Text');
    }
}
