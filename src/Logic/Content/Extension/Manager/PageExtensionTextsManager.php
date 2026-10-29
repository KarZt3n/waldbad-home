<?php

namespace App\Logic\Content\Extension\Manager;

use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Model\PageExtensionTexts;
use App\Logic\Content\Extension\PageExtensionTextsProcessorInterface;
use App\Logic\Content\Extension\PageExtensionTextsProviderInterface;

readonly class PageExtensionTextsManager implements PageExtensionTextsManagerInterface
{
    public function __construct(
        private PageExtensionTextsProviderInterface $provider,
        private PageExtensionTextsProcessorInterface $processor,
    ) {
    }

    public function resolve(PageExtension $extension): PageExtensionTexts
    {
        return $this->provider->find($extension) ?? new PageExtensionTexts($extension);
    }

    public function save(PageExtensionTexts $texts): PageExtensionTexts
    {
        return $this->processor->save($texts);
    }
}
