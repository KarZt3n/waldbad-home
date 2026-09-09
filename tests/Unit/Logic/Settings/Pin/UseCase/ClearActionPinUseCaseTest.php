<?php

namespace App\Tests\Unit\Logic\Settings\Pin\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Pin\Manager\PinSettingsManagerInterface;
use App\Logic\Settings\Pin\Model\PinSettings;
use App\Logic\Settings\Pin\Model\ProtectedAction;
use App\Logic\Settings\Pin\UseCase\ClearActionPinUseCase;
use PHPUnit\Framework\TestCase;

final class ClearActionPinUseCaseTest extends TestCase
{
    public function testRemovesTheOwnPinWhenAGlobalPinRemainsAsFallback(): void
    {
        $settings = new PinSettings('global-hash', [ProtectedAction::MembersDelete], [ProtectedAction::MembersDelete->value => 'own-hash']);
        $manager = $this->createMock(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn($settings);
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (PinSettings $saved): PinSettings {
                self::assertFalse($saved->hasOwnPin(ProtectedAction::MembersDelete));

                return $saved;
            },
        );

        (new ClearActionPinUseCase($manager))->execute(ProtectedAction::MembersDelete);
    }

    public function testRemovesTheOwnPinWhenTheActionIsNotCurrentlyProtected(): void
    {
        // Kein globaler PIN, aber die Aktion ist auch nicht geschützt — ein Aussperren ist damit
        // nicht möglich, das Entfernen ist also unbedenklich.
        $settings = new PinSettings(null, [], [ProtectedAction::MembersDelete->value => 'own-hash']);
        $manager = $this->createMock(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn($settings);
        $manager->expects(self::once())->method('save');

        (new ClearActionPinUseCase($manager))->execute(ProtectedAction::MembersDelete);
    }

    public function testRejectsRemovingTheOnlyPinOfACurrentlyProtectedAction(): void
    {
        $settings = new PinSettings(null, [ProtectedAction::MembersDelete], [ProtectedAction::MembersDelete->value => 'own-hash']);
        $manager = $this->createMock(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn($settings);
        $manager->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);

        (new ClearActionPinUseCase($manager))->execute(ProtectedAction::MembersDelete);
    }
}
