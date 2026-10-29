<?php

namespace App\Logic\Rental\Sauna\Duty\UseCase;

use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyWaiverManagerInterface;

/** Nimmt „kein Dienst nötig“ zurück: Der Zeitpunkt bzw. Tag braucht wieder Dienst. */
readonly class RemoveSaunaDutyWaiverUseCase
{
    public function __construct(private SaunaDutyWaiverManagerInterface $waivers)
    {
    }

    public function execute(string $id): void
    {
        $this->waivers->delete($this->waivers->get($id)->id);
    }
}
