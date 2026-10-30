<?php

namespace App\Logic\Content\Branding\Manager;

use App\Logic\Content\Branding\Model\SiteBranding;

interface SiteBrandingManagerInterface
{
    public function get(): SiteBranding;

    public function save(SiteBranding $branding): SiteBranding;
}
