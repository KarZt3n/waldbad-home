<?php

namespace App\Logic\Content\Branding\Manager;

use App\Logic\Content\Branding\Model\SiteBranding;
use App\Logic\Content\Branding\SiteBrandingProcessorInterface;
use App\Logic\Content\Branding\SiteBrandingProviderInterface;

readonly class SiteBrandingManager implements SiteBrandingManagerInterface
{
    public function __construct(
        private SiteBrandingProviderInterface $provider,
        private SiteBrandingProcessorInterface $processor,
    ) {
    }

    public function get(): SiteBranding
    {
        return $this->provider->get();
    }

    public function save(SiteBranding $branding): SiteBranding
    {
        return $this->processor->save($branding);
    }
}
