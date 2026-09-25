<?php

namespace App\Logic\Rental\Sauna\Season\UseCase;

use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;

/**
 * Bestehende Sauna-Anmeldungen bleiben beim Löschen einer Saison erhalten — sie speichern Datum
 * und Uhrzeit selbst und hängen nicht an der Saison.
 */
readonly class DeleteSaunaSeasonUseCase
{
    public function __construct(private SaunaSeasonManagerInterface $manager)
    {
    }

    public function execute(string $id): void
    {
        $this->manager->get($id);
        $this->manager->delete($id);
    }
}
