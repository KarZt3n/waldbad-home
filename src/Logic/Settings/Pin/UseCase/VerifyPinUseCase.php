<?php

namespace App\Logic\Settings\Pin\UseCase;

use App\Logic\Settings\Pin\Exception\PinRequiredException;
use App\Logic\Settings\Pin\Manager\PinSettingsManagerInterface;
use App\Logic\Settings\Pin\Model\ProtectedAction;

/**
 * Zentrale Prüfstelle für alle per PIN geschützten Module und Funktionalitäten. Ist $action aktuell
 * nicht geschützt, ist nichts zu prüfen (kein Fehler) — so kann jede Stelle im Code, die eine
 * potenziell geschützte Aktion ausführt (z. B. `AdminMemberController::delete()`), diese Prüfung
 * unbedingt aufrufen, ohne selbst wissen zu müssen, ob der Schutz gerade aktiv ist. Hat $action
 * einen eigenen PIN, zählt ausschließlich dieser — sonst der globale PIN (siehe
 * `PinSettings::matchesPin()`).
 */
readonly class VerifyPinUseCase
{
    public function __construct(private PinSettingsManagerInterface $manager)
    {
    }

    public function execute(ProtectedAction $action, ?string $pin): void
    {
        $settings = $this->manager->get();
        if (!$settings->isProtected($action)) {
            return;
        }
        if ($pin === null || !$settings->matchesPin($action, $pin)) {
            throw new PinRequiredException('Der eingegebene PIN ist nicht korrekt.');
        }
    }
}
