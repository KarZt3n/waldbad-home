<?php

namespace App\Logic\Membership\DirectDebit\UseCase;

use App\Logic\Membership\DirectDebit\Dto\DirectDebitCreditorResponse;
use App\Logic\Membership\DirectDebit\Dto\UpdateDirectDebitCreditorRequest;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitCreditorManagerInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;

readonly class UpdateDirectDebitCreditorUseCase
{
    public function __construct(private DirectDebitCreditorManagerInterface $manager)
    {
    }

    public function execute(UpdateDirectDebitCreditorRequest $request): DirectDebitCreditorResponse
    {
        $name = $request->name === null ? null : trim($request->name);
        $creditor = new DirectDebitCreditor($name === '' ? null : $name, $request->creditorId, $request->iban, $request->bic);

        return DirectDebitCreditorResponse::fromCreditor($this->manager->save($creditor));
    }
}
