<?php

namespace App\Logic\Content\Page\UseCase;

use App\Logic\Content\Page\Manager\PageManagerInterface;

readonly class DeletePageUseCase
{
    public function __construct(private PageManagerInterface $manager)
    {
    }

    public function execute(string $id): void
    {
        $page = $this->manager->get($id);
        $this->manager->ensureCanDelete($page);
        $this->manager->delete($id);
    }
}
