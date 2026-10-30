<?php

namespace App\Logic\Content\Branding;

use App\Logic\Content\Branding\Model\SiteBranding;

interface SiteBrandingProcessorInterface
{
    public function save(SiteBranding $branding): SiteBranding;
}
