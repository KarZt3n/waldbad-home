<?php

namespace App\Data\Membership\DirectDebit\Processor;

use App\Logic\Membership\DirectDebit\DirectDebitBookingTransactionInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineDirectDebitBookingTransaction implements DirectDebitBookingTransactionInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function execute(callable $work): void
    {
        $this->entityManager->wrapInTransaction(static function () use ($work): void {
            $work();
        });
    }
}
