<?php

namespace App\Logic\Content\Branding\Query;

use App\Logic\Common\SiteLogo;
use App\Logic\Content\Branding\Dto\SiteBrandingResponse;
use App\Logic\Content\Branding\Manager\SiteBrandingManagerInterface;

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
            defaultLogoUrl: SiteLogo::DEFAULT_URL,
        );
    }
}
