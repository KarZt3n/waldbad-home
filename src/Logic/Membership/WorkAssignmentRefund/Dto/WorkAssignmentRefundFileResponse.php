<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Dto;

readonly class WorkAssignmentRefundFileResponse
{
    public function __construct(
        public string $fileName,
        public string $content,
    ) {
    }
}
