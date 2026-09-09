<?php

namespace App\Tests\Unit\Logic\Settings\Pin\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Pin\Manager\PinSettingsManagerInterface;
use App\Logic\Settings\Pin\Model\PinSettings;
use App\Logic\Settings\Pin\Model\ProtectedAction;
use App\Logic\Settings\Pin\Service\PinHasher;
use App\Logic\Settings\Pin\UseCase\SetActionPinUseCase;
use PHPUnit\Framework\TestCase;

final class SetActionPinUseCaseTest extends TestCase
{
    public function testStoresAHashedPinForTheGivenActionOnly(): void
    {
        $manager = $this->createMock(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings('global-hash', [], []));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (PinSettings $settings): PinSettings {
                self::assertSame('global-hash', $settings->globalPinHash);
                self::assertTrue($settings->hasOwnPin(ProtectedAction::MembersDelete));
                self::assertFalse($settings->hasOwnPin(ProtectedAction::MembersModuleAccess));
                self::assertTrue(password_verify('9999', $settings->actionPinHashes[ProtectedAction::MembersDelete->value]));

                return $settings;
            },
        );

        (new SetActionPinUseCase($manager, new PinHasher()))->execute(ProtectedAction::MembersDelete, '9999');
    }

    public function testRejectsAnInvalidPinFormat(): void
    {
        $manager = $this->createStub(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings(null, [], []));

        $this->expectException(BusinessRuleViolationException::class);

        (new SetActionPinUseCase($manager, new PinHasher()))->execute(ProtectedAction::MembersDelete, 'abc');
    }
}
