<?php

namespace App\Logic\Settings\Pin\Service;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Gemeinsame Validierung/Hashing für jeden PIN — den globalen wie auch je Aktion abweichende
 * (siehe `SetGlobalPinUseCase`, `SetActionPinUseCase`).
 */
readonly class PinHasher
{
    public function hash(string $pin): string
    {
        if (preg_match('/^\d{4,8}$/', $pin) !== 1) {
            throw new BusinessRuleViolationException('Der PIN muss aus 4 bis 8 Ziffern bestehen.');
        }

        return password_hash($pin, PASSWORD_DEFAULT);
    }
}
