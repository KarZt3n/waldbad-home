<?php

namespace App\Logic\Content\Extension;

use App\Logic\Content\Extension\Model\PageExtensionTexts;

interface PageExtensionTextsProcessorInterface
{
    public function save(PageExtensionTexts $texts): PageExtensionTexts;
}
