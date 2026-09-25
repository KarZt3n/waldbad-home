<?php

namespace App\Logic\Rental\Sauna\Terms\Query;

use App\Logic\Rental\Sauna\Terms\Dto\SaunaTermsResponse;
use App\Logic\Rental\Sauna\Terms\Manager\SaunaTermsManagerInterface;

readonly class GetSaunaTermsQuery
{
    public function __construct(private SaunaTermsManagerInterface $manager)
    {
    }

    public function execute(): SaunaTermsResponse
    {
        return SaunaTermsResponse::fromTerms($this->manager->current());
    }
}
