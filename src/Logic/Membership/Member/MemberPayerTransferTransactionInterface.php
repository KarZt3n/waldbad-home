<?php

namespace App\Logic\Membership\Member;

/** Führt eine Zahler-Übertragung (ggf. mit anschließendem Löschen) atomar aus, siehe `PayerTransfer`. */
interface MemberPayerTransferTransactionInterface
{
    /** @param callable(): void $work */
    public function execute(callable $work): void;
}
