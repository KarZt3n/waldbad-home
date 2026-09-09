<?php

namespace App\Logic\Settings\Pin\UseCase;

use App\Logic\Settings\Pin\Manager\PinSettingsManagerInterface;
use App\Logic\Settings\Pin\Service\PinHasher;

/**
 * Legt den globalen PIN neu an oder ersetzt ihn — der Rückfall für jede geschützte Aktion, die
 * keinen eigenen, abweichenden PIN hat (siehe `SetActionPinUseCase`). Ändert nicht, welche
 * Aktionen aktuell geschützt sind (siehe `UpdateProtectedActionsUseCase`).
 */
readonly class SetGlobalPinUseCase
{
    public function __construct(
        private PinSettingsManagerInterface $manager,
        private PinHasher $hasher,
    ) {
    }

    public function execute(string $pin): void
    {
        $hash = $this->hasher->hash($pin);
        $settings = $this->manager->get();
        $this->manager->save($settings->withGlobalPinHash($hash));
    }
}
