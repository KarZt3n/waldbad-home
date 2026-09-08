<?php

namespace App\Logic\Membership\Member;

interface MemberImportTransactionInterface
{
    /** @param callable(): void $work */
    public function execute(callable $work): void;
}
