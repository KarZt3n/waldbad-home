<?php

namespace App\Logic\Content\Branding\Query;

use App\Logic\Content\Branding\Dto\SiteBrandingResponse;
use App\Logic\Content\Branding\Manager\SiteBrandingManagerInterface;
use App\Logic\Content\Branding\Model\SiteBranding;

readonly class GetSiteBrandingQuery
{
    public function __construct(private SiteBrandingManagerInterface $manager)
    {
    }

    public function execute(): SiteBrandingResponse
    {
        $branding = $this->manager->get();

        return new SiteBrandingResponse(
            logoUrl: $branding->logoUrl(),
            customLogoUrl: $branding->customLogoUrl,
            defaultLogoUrl: SiteBranding::DEFAULT_LOGO_URL,
        );
    }
}
