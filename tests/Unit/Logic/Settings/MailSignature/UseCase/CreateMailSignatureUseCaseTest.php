<?php

namespace App\Tests\Unit\Logic\Settings\MailSignature\UseCase;

use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailSignature\Model\MailSignature;
use App\Logic\Settings\MailSignature\UseCase\CreateMailSignatureUseCase;
use PHPUnit\Framework\TestCase;

final class CreateMailSignatureUseCaseTest extends TestCase
{
    public function testCreatesASignatureWithAGeneratedId(): void
    {
        $identifierGenerator = $this->createStub(IdentifierGeneratorInterface::class);
        $identifierGenerator->method('generate')->willReturn('id-1');

        $manager = $this->createMock(MailSignatureManagerInterface::class);
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (MailSignature $signature): MailSignature {
                self::assertSame('id-1', $signature->id);
                self::assertSame('Standard', $signature->name);
                self::assertSame('Freundliche Grüße', $signature->body);

                return $signature;
            },
        );

        $response = (new CreateMailSignatureUseCase($manager, $identifierGenerator))->execute('Standard', 'Freundliche Grüße');

        self::assertSame('id-1', $response->id);
        self::assertSame('Standard', $response->name);
    }
}
