<?php

namespace App\Tests\Unit\Logic\Settings\Pin\UseCase;

use App\Logic\Settings\Pin\Exception\PinRequiredException;
use App\Logic\Settings\Pin\Manager\PinSettingsManagerInterface;
use App\Logic\Settings\Pin\Model\PinSettings;
use App\Logic\Settings\Pin\Model\ProtectedAction;
use App\Logic\Settings\Pin\UseCase\VerifyPinUseCase;
use PHPUnit\Framework\TestCase;

final class VerifyPinUseCaseTest extends TestCase
{
    public function testDoesNothingWhenTheActionIsNotCurrentlyProtected(): void
    {
        $manager = $this->createStub(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings(null, [], []));

        (new VerifyPinUseCase($manager))->execute(ProtectedAction::MembersDelete, null);

        $this->expectNotToPerformAssertions();
    }

    public function testThrowsWhenProtectedAndNoPinWasSubmitted(): void
    {
        $manager = $this->createStub(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings(password_hash('1234', PASSWORD_DEFAULT), [ProtectedAction::MembersDelete], []));

        $this->expectException(PinRequiredException::class);

        (new VerifyPinUseCase($manager))->execute(ProtectedAction::MembersDelete, null);
    }

    public function testThrowsWhenProtectedAndTheSubmittedPinIsWrong(): void
    {
        $manager = $this->createStub(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings(password_hash('1234', PASSWORD_DEFAULT), [ProtectedAction::MembersDelete], []));

        $this->expectException(PinRequiredException::class);

        (new VerifyPinUseCase($manager))->execute(ProtectedAction::MembersDelete, '9999');
    }

    public function testPassesWhenProtectedAndTheSubmittedGlobalPinIsCorrect(): void
    {
        $manager = $this->createStub(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings(password_hash('1234', PASSWORD_DEFAULT), [ProtectedAction::MembersDelete], []));

        (new VerifyPinUseCase($manager))->execute(ProtectedAction::MembersDelete, '1234');

        $this->expectNotToPerformAssertions();
    }

    public function testThrowsWhenProtectedButNoPinHasEverBeenSet(): void
    {
        // Theoretisch nicht erreichbar (UpdateProtectedActionsUseCase verhindert das Schützen ohne
        // jeden PIN), zur Sicherheit trotzdem getestet: kein Hash bedeutet nie „durchgelassen“.
        $manager = $this->createStub(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn(new PinSettings(null, [ProtectedAction::MembersDelete], []));

        $this->expectException(PinRequiredException::class);

        (new VerifyPinUseCase($manager))->execute(ProtectedAction::MembersDelete, '1234');
    }

    public function testAnOwnPinOverridesTheGlobalPinForThatAction(): void
    {
        $settings = new PinSettings(
            password_hash('1111', PASSWORD_DEFAULT),
            [ProtectedAction::MembersDelete],
            [ProtectedAction::MembersDelete->value => password_hash('2222', PASSWORD_DEFAULT)],
        );
        $manager = $this->createStub(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn($settings);

        $this->expectException(PinRequiredException::class);

        // Der globale PIN „1111“ darf hier nicht mehr greifen — nur noch der eigene „2222“.
        (new VerifyPinUseCase($manager))->execute(ProtectedAction::MembersDelete, '1111');
    }

    public function testPassesWithTheOwnPinWhenOneIsSetForTheAction(): void
    {
        $settings = new PinSettings(
            password_hash('1111', PASSWORD_DEFAULT),
            [ProtectedAction::MembersDelete],
            [ProtectedAction::MembersDelete->value => password_hash('2222', PASSWORD_DEFAULT)],
        );
        $manager = $this->createStub(PinSettingsManagerInterface::class);
        $manager->method('get')->willReturn($settings);

        (new VerifyPinUseCase($manager))->execute(ProtectedAction::MembersDelete, '2222');

        $this->expectNotToPerformAssertions();
    }
}
