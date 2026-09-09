<?php

namespace App\Logic\Settings\Pin;

use App\Logic\Settings\Pin\Model\PinSettings;

interface PinSettingsProviderInterface
{
    /**
     * Liefert immer einen Datensatz, auch wenn noch nie gespeichert wurde (dann ohne PIN und ohne
     * geschützte Aktionen) — Aufrufer müssen den „noch nicht angelegt“-Fall nicht gesondert
     * behandeln.
     */
    public function get(): PinSettings;
}
