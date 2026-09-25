<?php

namespace App\Logic\Rental\Sauna\Terms\Manager;

use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;

interface SaunaTermsManagerInterface
{
    /** Aktuell gültige Konditionen; ohne gespeicherten Stand die Ausgangswerte (`SaunaTerms::defaults()`). */
    public function current(): SaunaTerms;

    public function save(SaunaTerms $terms): SaunaTerms;
}
