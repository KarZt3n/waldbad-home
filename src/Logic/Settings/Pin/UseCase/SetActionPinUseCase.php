<?php

namespace App\Logic\Settings\Pin\UseCase;

use App\Logic\Settings\Pin\Manager\PinSettingsManagerInterface;
use App\Logic\Settings\Pin\Model\ProtectedAction;
use App\Logic\Settings\Pin\Service\PinHasher;

/**
 * Legt für eine einzelne `ProtectedAction` einen eigenen, vom globalen PIN abweichenden PIN fest
 * (z. B. ein strengerer PIN nur für „Mitglied löschen“). Solange kein eigener PIN hinterlegt ist,
 * gilt für die Aktion weiterhin der globale PIN (siehe `PinSettings::matchesPin()`).
 */
readonly class SetActionPinUseCase
{
    public function __construct(
        private PinSettingsManagerInterface $manager,
        private PinHasher $hasher,
    ) {
    }

    public function execute(ProtectedAction $action, string $pin): void
    {
        $hash = $this->hasher->hash($pin);
        $settings = $this->manager->get();
        $this->manager->save($settings->withActionPinHash($action, $hash));
    }
}
