<?php

namespace App\Logic\Membership\DirectDebit\Dto;

readonly class ExportAllDirectDebitsRequest
{
    /**
     * @param list<string> $payerIds die in der Übersicht als exportierbar angezeigten Zahler — schützt
     *                               davor, dass sich der Datenstand seit dem Öffnen der Übersicht
     *                               geändert hat und unbemerkt andere Zahler abgebucht werden
     */
    public function __construct(
        public array $payerIds,
    ) {
    }
}
