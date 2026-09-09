<?php

namespace App\Logic\Settings\Pin\Query;

use App\Logic\Settings\Pin\Dto\PinSettingsResponse;
use App\Logic\Settings\Pin\Manager\PinSettingsManagerInterface;
use App\Logic\Settings\Pin\Model\ProtectedAction;

readonly class GetPinSettingsQuery
{
    public function __construct(private PinSettingsManagerInterface $manager)
    {
    }

    public function execute(): PinSettingsResponse
    {
        $settings = $this->manager->get();

        return new PinSettingsResponse(
            globalPinIsSet: $settings->globalPinHash !== null,
            protectedActions: array_map(static fn (ProtectedAction $action): string => $action->value, $settings->protectedActions),
            actionsWithOwnPin: array_values(array_map(
                static fn (ProtectedAction $action): string => $action->value,
                array_filter(ProtectedAction::cases(), $settings->hasOwnPin(...)),
            )),
        );
    }
}
