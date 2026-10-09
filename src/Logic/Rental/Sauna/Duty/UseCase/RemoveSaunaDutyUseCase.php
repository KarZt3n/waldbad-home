<?php

namespace App\Logic\Rental\Sauna\Duty\UseCase;

use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyManagerInterface;

readonly class RemoveSaunaDutyUseCase
{
    public function __construct(private SaunaDutyManagerInterface $duties)
    {
    }

    public function execute(string $id): void
    {
        $this->duties->delete($this->duties->get($id)->id);
    }
}
