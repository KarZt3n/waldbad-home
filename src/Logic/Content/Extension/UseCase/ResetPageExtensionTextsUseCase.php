<?php

namespace App\Logic\Content\Extension\UseCase;

use App\Logic\Content\Extension\Manager\PageExtensionTextsManagerInterface;
use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Model\PageExtensionTexts;

readonly class ResetPageExtensionTextsUseCase
{
    public function __construct(private PageExtensionTextsManagerInterface $manager)
    {
    }

    public function execute(PageExtension $extension): void
    {
        $this->manager->save(new PageExtensionTexts($extension));
    }
}
