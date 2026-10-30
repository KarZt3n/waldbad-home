<?php

namespace App\Logic\Content\Branding;

use App\Logic\Content\Branding\Model\SiteBranding;

interface SiteBrandingProviderInterface
{
    /**
     * Liefert immer einen Datensatz, auch wenn noch nie gespeichert wurde (dann mit Standardlogo).
     */
    public function get(): SiteBranding;
}
