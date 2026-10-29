<?php

namespace App\Logic\Content\Extension\UseCase;

use App\Logic\Content\Extension\Manager\PageExtensionTextsManagerInterface;
use App\Logic\Content\Extension\Model\PageExtension;

readonly class UpdatePageExtensionTextsUseCase
{
    public function __construct(private PageExtensionTextsManagerInterface $manager)
    {
    }

    /**
     * @param array<string, string> $texts nur die übermittelten Felder werden geändert
     */
    public function execute(PageExtension $extension, array $texts): void
    {
        $this->manager->save($this->manager->resolve($extension)->withTexts($texts));
    }
}
