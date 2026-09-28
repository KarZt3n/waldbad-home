<?php

namespace App\Logic\Membership\DirectDebit\Manager;

use App\Logic\Membership\DirectDebit\DirectDebitCreditorProcessorInterface;
use App\Logic\Membership\DirectDebit\DirectDebitCreditorProviderInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;

readonly class DirectDebitCreditorManager implements DirectDebitCreditorManagerInterface
{
    public function __construct(
        private DirectDebitCreditorProviderInterface $provider,
        private DirectDebitCreditorProcessorInterface $processor,
    ) {
    }

    public function get(): DirectDebitCreditor
    {
        return $this->provider->get();
    }

    public function save(DirectDebitCreditor $creditor): DirectDebitCreditor
    {
        return $this->processor->save($creditor);
    }
}
