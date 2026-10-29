<?php

namespace App\Logic\Content\Extension;

use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Model\PageExtensionTexts;

interface PageExtensionTextsProviderInterface
{
    public function find(PageExtension $extension): ?PageExtensionTexts;
}
