<?php

namespace App\Logic\Rental\Sauna\Terms;

use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;

interface SaunaTermsProviderInterface
{
    /** Gespeicherte Konditionen oder `null`, solange noch keine gespeichert wurden. */
    public function find(): ?SaunaTerms;
}
