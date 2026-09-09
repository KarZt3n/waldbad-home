<?php

namespace App\Logic\Settings\Pin\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Pin\Manager\PinSettingsManagerInterface;
use App\Logic\Settings\Pin\Model\ProtectedAction;

/**
 * Entfernt den eigenen PIN einer `ProtectedAction` wieder — die Aktion fällt danach auf den
 * globalen PIN zurück. Ist die Aktion aktuell geschützt UND kein globaler PIN hinterlegt, würde
 * das Entfernen die Aktion für alle unerreichbar machen (kein PIN könnte je passen); das wird
 * deshalb abgelehnt, statt einen Aussperr-Zustand zu erzeugen.
 */
readonly class ClearActionPinUseCase
{
    public function __construct(private PinSettingsManagerInterface $manager)
    {
    }

    public function execute(ProtectedAction $action): void
    {
        $settings = $this->manager->get();
        if ($settings->isProtected($action) && $settings->globalPinHash === null) {
            throw new BusinessRuleViolationException(
                'Ohne globalen PIN kann der eigene PIN dieser geschützten Aktion nicht entfernt werden — '
                .'sie wäre sonst für niemanden mehr erreichbar. Bitte zuerst einen globalen PIN festlegen '
                .'oder den Schutz für diese Aktion aufheben.',
            );
        }

        $this->manager->save($settings->withActionPinHash($action, null));
    }
}
