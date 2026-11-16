<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Dto\TransferMemberPayerRequest;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\MemberPayerTransferTransactionInterface;
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;
use App\Logic\Membership\Member\Service\PayerTransfer;

/**
 * „Zahler übertragen…“ ohne Löschen, z. B. wenn der bisherige Zahler ausgetreten ist und die
 * übrige Familie bleibt: Der bisherige Zahler bleibt als Datensatz erhalten und zahlt künftig selbst
 * über den Nachfolger (siehe `PayerTransfer`). Anschließend werden die Beiträge des Haushalts neu
 * berechnet — alles in einer Transaktion.
 */
readonly class TransferMemberPayerUseCase
{
    public function __construct(
        private MemberManagerInterface $manager,
        private PayerTransfer $payerTransfer,
        private HouseholdContributionRecalculator $recalculator,
        private MemberPayerTransferTransactionInterface $transaction,
    ) {
    }

    public function execute(TransferMemberPayerRequest $request): MemberResponse
    {
        $member = $this->manager->get($request->memberId);
        $this->transaction->execute(function () use ($member, $request): void {
            $successor = $this->payerTransfer->transfer(
                $member,
                $request->successorMemberId,
                $request->keepSuccessorPaymentDetails,
                $request->transferPrimaryMembership,
                previousStays: true,
            );
            $this->recalculator->recalculate($successor);
        });

        return MemberResponse::fromMember($this->manager->get($request->memberId));
    }
}
