<?php

namespace App\Logic\Contact\Request\UseCase;

use App\Logic\Contact\Request\Manager\ContactRequestManagerInterface;

readonly class DeleteContactRequestUseCase
{
    public function __construct(private ContactRequestManagerInterface $manager)
    {
    }

    public function execute(string $id): void
    {
        $this->manager->delete($this->manager->get($id));
    }
}
