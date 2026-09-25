<?php

namespace App\Logic\Rental\Sauna\Terms\Manager;

use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;
use App\Logic\Rental\Sauna\Terms\SaunaTermsProcessorInterface;
use App\Logic\Rental\Sauna\Terms\SaunaTermsProviderInterface;

readonly class SaunaTermsManager implements SaunaTermsManagerInterface
{
    public function __construct(
        private SaunaTermsProviderInterface $provider,
        private SaunaTermsProcessorInterface $processor,
    ) {
    }

    public function current(): SaunaTerms
    {
        return $this->provider->find() ?? SaunaTerms::defaults();
    }

    public function save(SaunaTerms $terms): SaunaTerms
    {
        return $this->processor->save($terms);
    }
}
