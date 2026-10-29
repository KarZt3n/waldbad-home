<?php

namespace App\Logic\Content\Extension\Manager;

use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Model\PageExtensionTexts;

interface PageExtensionTextsManagerInterface
{
    /** Liefert immer Texte, ohne gespeicherte Anpassungen mit den Standardtexten. */
    public function resolve(PageExtension $extension): PageExtensionTexts;

    public function save(PageExtensionTexts $texts): PageExtensionTexts;
}
