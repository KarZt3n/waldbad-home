<?php

namespace App\Tests\Unit\Logic\Settings\Pin\Model;

use App\Logic\Settings\Pin\Model\PinSettings;
use App\Logic\Settings\Pin\Model\ProtectedAction;
use PHPUnit\Framework\TestCase;

final class PinSettingsTest extends TestCase
{
    public function testIsProtectedReflectsTheStoredActionList(): void
    {
        $settings = new PinSettings(null, [ProtectedAction::MembersDelete], []);

        self::assertTrue($settings->isProtected(ProtectedAction::MembersDelete));
        self::assertFalse($settings->isProtected(ProtectedAction::MembersModuleAccess));
    }

    public function testHasOwnPinAndHasAnyPin(): void
    {
        $noPinAtAll = new PinSettings(null, [], []);
        $onlyGlobal = new PinSettings('global-hash', [], []);
        $onlyOwn = new PinSettings(null, [], [ProtectedAction::MembersDelete->value => 'own-hash']);

        self::assertFalse($noPinAtAll->hasOwnPin(ProtectedAction::MembersDelete));
        self::assertFalse($noPinAtAll->hasAnyPin(ProtectedAction::MembersDelete));

        self::assertFalse($onlyGlobal->hasOwnPin(ProtectedAction::MembersDelete));
        self::assertTrue($onlyGlobal->hasAnyPin(ProtectedAction::MembersDelete));

        self::assertTrue($onlyOwn->hasOwnPin(ProtectedAction::MembersDelete));
        self::assertTrue($onlyOwn->hasAnyPin(ProtectedAction::MembersDelete));
        self::assertFalse($onlyOwn->hasAnyPin(ProtectedAction::MembersModuleAccess));
    }

    public function testMatchesPinFallsBackToTheGlobalPinWithoutAnOwnOne(): void
    {
        $settings = new PinSettings(password_hash('1234', PASSWORD_DEFAULT), [], []);

        self::assertTrue($settings->matchesPin(ProtectedAction::MembersDelete, '1234'));
        self::assertFalse($settings->matchesPin(ProtectedAction::MembersDelete, '9999'));
    }

    public function testMatchesPinPrefersTheOwnPinOverTheGlobalOne(): void
    {
        $settings = new PinSettings(
            password_hash('1111', PASSWORD_DEFAULT),
            [],
            [ProtectedAction::MembersDelete->value => password_hash('2222', PASSWORD_DEFAULT)],
        );

        self::assertTrue($settings->matchesPin(ProtectedAction::MembersDelete, '2222'));
        self::assertFalse($settings->matchesPin(ProtectedAction::MembersDelete, '1111'));
        // Für eine andere Aktion ohne eigenen PIN gilt weiterhin der globale.
        self::assertTrue($settings->matchesPin(ProtectedAction::MembersModuleAccess, '1111'));
    }

    public function testWithersReturnNewImmutableInstances(): void
    {
        $settings = new PinSettings(null, [], []);

        $withGlobal = $settings->withGlobalPinHash('hash');
        $withActions = $settings->withProtectedActions([ProtectedAction::MembersDelete]);
        $withOwnPin = $settings->withActionPinHash(ProtectedAction::MembersDelete, 'own-hash');

        self::assertNull($settings->globalPinHash);
        self::assertSame([], $settings->protectedActions);
        self::assertSame([], $settings->actionPinHashes);
        self::assertSame('hash', $withGlobal->globalPinHash);
        self::assertSame([ProtectedAction::MembersDelete], $withActions->protectedActions);
        self::assertSame(['members.delete' => 'own-hash'], $withOwnPin->actionPinHashes);
    }

    public function testWithActionPinHashNullRemovesTheOwnPinAgain(): void
    {
        $settings = new PinSettings(null, [], [ProtectedAction::MembersDelete->value => 'own-hash']);

        $cleared = $settings->withActionPinHash(ProtectedAction::MembersDelete, null);

        self::assertSame([], $cleared->actionPinHashes);
    }
}
