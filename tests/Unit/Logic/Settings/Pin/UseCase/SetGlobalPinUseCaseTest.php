<?php

namespace App\Tests\Unit\Logic\Settings\Pin\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Pin\Manager\PinSettingsManagerInterface;
use App\Logic\Settings\Pin\Model\PinSettings;
use App\Logic\Settings\Pin\Service\PinHasher;
use App\Logic\Settings\Pin\UseCase\SetGlobalPinUseCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SetGlobalPinUseCaseTest extends TestCase
{
    public function testStoresAHashedGlobalPin(): void
    {
        $manager = $this->createMock(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings(null, [], []));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (PinSettings $settings): PinSettings {
                self::assertNotNull($settings->globalPinHash);
                self::assertNotSame('1234', $settings->globalPinHash);
                self::assertTrue(password_verify('1234', $settings->globalPinHash));

                return $settings;
            },
        );

        (new SetGlobalPinUseCase($manager, new PinHasher()))->execute('1234');
    }

    #[DataProvider('invalidPinProvider')]
    public function testRejectsPinsThatAreNotFourToEightDigits(string $pin): void
    {
        $manager = $this->createStub(PinSettingsManagerInterface::class);

        $this->expectException(BusinessRuleViolationException::class);

        (new SetGlobalPinUseCase($manager, new PinHasher()))->execute($pin);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPinProvider(): iterable
    {
        yield 'too short' => ['123'];
        yield 'too long' => ['123456789'];
        yield 'not numeric' => ['abcd'];
        yield 'empty' => [''];
    }
}
