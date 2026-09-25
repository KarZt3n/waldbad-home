<?php

namespace App\Logic\Rental\Sauna\Terms;

use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;

interface SaunaTermsProcessorInterface
{
    public function save(SaunaTerms $terms): SaunaTerms;
}
