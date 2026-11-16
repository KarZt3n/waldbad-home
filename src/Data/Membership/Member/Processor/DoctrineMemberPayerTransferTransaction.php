<?php

namespace App\Data\Membership\Member\Processor;

use App\Logic\Membership\Member\MemberPayerTransferTransactionInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMemberPayerTransferTransaction implements MemberPayerTransferTransactionInterface
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
