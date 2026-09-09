<?php

namespace App\Logic\Settings\Pin\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Pin\Manager\PinSettingsManagerInterface;
use App\Logic\Settings\Pin\Model\ProtectedAction;

/**
 * Legt fest, welche `ProtectedAction`s aktuell per PIN abgesichert sind. Für jede neu geschützte
 * Aktion muss dabei entweder ein eigener PIN (siehe `SetActionPinUseCase`) oder der globale PIN
 * (siehe `SetGlobalPinUseCase`) bereits hinterlegt sein — ein Schutz ohne jeden PIN wäre
 * wirkungslos (jeder käme mit einem leeren Feld durch) und würde Admins fälschlich in Sicherheit
 * wiegen.
 */
readonly class UpdateProtectedActionsUseCase
{
    public function __construct(private PinSettingsManagerInterface $manager)
    {
    }

    /**
     * @param list<string> $actionKeys
     */
    public function execute(array $actionKeys): void
    {
        $actions = array_map(
            static fn (string $key): ProtectedAction => ProtectedAction::tryFrom($key)
                ?? throw new BusinessRuleViolationException(sprintf('Unbekannter Schlüssel "%s".', $key)),
            $actionKeys,
        );
        $actions = array_values(array_unique($actions, SORT_REGULAR));

        $settings = $this->manager->get();
        foreach ($actions as $action) {
            if (!$settings->hasAnyPin($action)) {
                throw new BusinessRuleViolationException(sprintf(
                    'Für „%s“ ist weder ein eigener noch ein globaler PIN hinterlegt. Bitte zuerst einen PIN festlegen, bevor diese Funktion damit geschützt wird.',
                    $action->label(),
                ));
            }
        }

        $this->manager->save($settings->withProtectedActions($actions));
    }
}
