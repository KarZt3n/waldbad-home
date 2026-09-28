<?php

namespace App\Logic\Membership\DirectDebit\Dto;

readonly class DirectDebitFileResponse
{
    public function __construct(
        public string $fileName,
        public string $content,
    ) {
    }
}
