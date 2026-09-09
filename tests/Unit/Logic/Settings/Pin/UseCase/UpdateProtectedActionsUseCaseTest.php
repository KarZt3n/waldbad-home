<?php

namespace App\Tests\Unit\Logic\Settings\Pin\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Pin\Manager\PinSettingsManagerInterface;
use App\Logic\Settings\Pin\Model\PinSettings;
use App\Logic\Settings\Pin\Model\ProtectedAction;
use App\Logic\Settings\Pin\UseCase\UpdateProtectedActionsUseCase;
use PHPUnit\Framework\TestCase;

final class UpdateProtectedActionsUseCaseTest extends TestCase
{
    public function testStoresTheSelectedActionsWhenAGlobalPinIsAlreadySet(): void
    {
        $manager = $this->createMock(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings('hash', [], []));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (PinSettings $settings): PinSettings {
                self::assertSame([ProtectedAction::MembersDelete], $settings->protectedActions);

                return $settings;
            },
        );

        (new UpdateProtectedActionsUseCase($manager))->execute(['members.delete']);
    }

    public function testStoresTheSelectedActionWhenOnlyItsOwnPinIsSetWithoutAGlobalPin(): void
    {
        $manager = $this->createMock(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings(null, [], [ProtectedAction::MembersDelete->value => 'own-hash']));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (PinSettings $settings): PinSettings {
                self::assertSame([ProtectedAction::MembersDelete], $settings->protectedActions);

                return $settings;
            },
        );

        (new UpdateProtectedActionsUseCase($manager))->execute(['members.delete']);
    }

    public function testRejectsProtectingAnythingWithoutAnyPinSet(): void
    {
        $manager = $this->createMock(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings(null, [], []));
        $manager->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);

        (new UpdateProtectedActionsUseCase($manager))->execute(['members.delete']);
    }

    public function testClearingTheListIsAllowedEvenWithoutAPinSet(): void
    {
        $manager = $this->createMock(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings(null, [ProtectedAction::MembersDelete], []));
        $manager->expects(self::once())->method('save')->willReturnCallback(
            static function (PinSettings $settings): PinSettings {
                self::assertSame([], $settings->protectedActions);

                return $settings;
            },
        );

        (new UpdateProtectedActionsUseCase($manager))->execute([]);
    }

    public function testRejectsUnknownActionKeys(): void
    {
        $manager = $this->createStub(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings('hash', [], []));

        $this->expectException(BusinessRuleViolationException::class);

        (new UpdateProtectedActionsUseCase($manager))->execute(['not.a.real.action']);
    }
}
