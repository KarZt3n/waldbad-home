<?php

namespace App\Logic\Membership\DirectDebit\Query;

use App\Logic\Membership\DirectDebit\Dto\DirectDebitCreditorResponse;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitCreditorManagerInterface;

readonly class GetDirectDebitCreditorQuery
{
    public function __construct(private DirectDebitCreditorManagerInterface $manager)
    {
    }

    public function execute(): DirectDebitCreditorResponse
    {
        return DirectDebitCreditorResponse::fromCreditor($this->manager->get());
    }
}
