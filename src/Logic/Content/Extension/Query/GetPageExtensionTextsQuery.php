<?php

namespace App\Logic\Content\Extension\Query;

use App\Logic\Content\Extension\Dto\PageExtensionTextsResponse;
use App\Logic\Content\Extension\Manager\PageExtensionTextsManagerInterface;
use App\Logic\Content\Extension\Model\PageExtension;

readonly class GetPageExtensionTextsQuery
{
    public function __construct(private PageExtensionTextsManagerInterface $manager)
    {
    }

    public function execute(PageExtension $extension): PageExtensionTextsResponse
    {
        $texts = $this->manager->resolve($extension);

        return new PageExtensionTextsResponse($extension->value, $texts->resolved(), $texts->consentVersion());
    }
}
