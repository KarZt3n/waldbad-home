<?php

namespace App\Logic\Membership\Member\Dto;

final readonly class MandateBackfillResult
{
    public function __construct(
        public int $updated,
        public int $unchanged,
        public int $notFound,
    ) {
    }
}
