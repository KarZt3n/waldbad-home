<?php

namespace App\Logic\Event\HelpRequest;

use App\Logic\Event\HelpRequest\Model\EventHelpRequest;

interface EventHelpRequestProcessorInterface
{
    public function save(EventHelpRequest $request): EventHelpRequest;

    /**
     * Wird nur für das Zusammenführen doppelter Anmeldungen genutzt (siehe
     * `EventHelpRequestDuplicateMerger`), nicht für eine eigene Lösch-Aktion in der Oberfläche.
     */
    public function delete(string $id): void;
}
