<?php

namespace App\Logic\Membership\ContributionRate;

use App\Logic\Membership\ContributionRate\Model\ContributionRateSettings;

interface ContributionRateSettingsProviderInterface
{
    /**
     * Liefert immer einen Datensatz, auch wenn noch nie gespeichert wurde (dann ohne Datum) —
     * Aufrufer müssen den „noch nicht angelegt“-Fall nicht gesondert behandeln.
     */
    public function get(): ContributionRateSettings;
}
