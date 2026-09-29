<?php

namespace App\Logic\Membership\DirectDebit\Model;

/** Ein Grund, warum eine Lastschrift nicht exportiert werden kann (siehe `PayerDirectDebitDraft`). */
readonly class DirectDebitObstacle
{
    public function __construct(
        public DirectDebitObstacleKind $kind,
        public string $message,
    ) {
    }
}
