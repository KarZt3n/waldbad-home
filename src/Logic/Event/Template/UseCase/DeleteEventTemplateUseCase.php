<?php

namespace App\Logic\Event\Template\UseCase;

use App\Logic\Event\Template\Manager\EventTemplateManagerInterface;

readonly class DeleteEventTemplateUseCase
{
    public function __construct(private EventTemplateManagerInterface $manager)
    {
    }

    public function execute(string $id): void
    {
        $this->manager->get($id);
        $this->manager->delete($id);
    }
}
