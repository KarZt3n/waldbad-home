<?php

namespace App\Tests\Unit\Logic\Settings\MailSignature\UseCase;

use App\Logic\Settings\MailSignature\Exception\MailSignatureNotFoundException;
use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailSignature\Model\MailSignature;
use App\Logic\Settings\MailSignature\UseCase\DeleteMailSignatureUseCase;
use PHPUnit\Framework\TestCase;

final class DeleteMailSignatureUseCaseTest extends TestCase
{
    public function testDeletesAnExistingSignature(): void
    {
        $manager = $this->createMock(MailSignatureManagerInterface::class);
        $manager->method('get')->willReturn(new MailSignature('id-1', 'Name', 'Text'));
        $manager->expects(self::once())->method('delete')->with('id-1');

        (new DeleteMailSignatureUseCase($manager))->execute('id-1');
    }

    public function testFailsForAnUnknownSignatureWithoutDeleting(): void
    {
        $manager = $this->createMock(MailSignatureManagerInterface::class);
        $manager->method('get')->willThrowException(new MailSignatureNotFoundException('missing'));
        $manager->expects(self::never())->method('delete');

        $this->expectException(MailSignatureNotFoundException::class);

        (new DeleteMailSignatureUseCase($manager))->execute('missing');
    }
}
