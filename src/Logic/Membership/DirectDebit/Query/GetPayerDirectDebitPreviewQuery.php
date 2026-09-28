<?php

namespace App\Logic\Membership\DirectDebit\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\DirectDebit\Dto\PayerDirectDebitPreviewResponse;
use App\Logic\Membership\DirectDebit\Service\PayerDirectDebitPlanner;

/** Vorschau der Lastschrift für den Zahler des angefragten Mitglieds (siehe `PayerDirectDebitPlanner`). */
readonly class GetPayerDirectDebitPreviewQuery
{
    public function __construct(
        private PayerDirectDebitPlanner $planner,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $memberId): PayerDirectDebitPreviewResponse
    {
        return PayerDirectDebitPreviewResponse::fromDraft($this->planner->plan($memberId, $this->clock->now()));
    }
}
