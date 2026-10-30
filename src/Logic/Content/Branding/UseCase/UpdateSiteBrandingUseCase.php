<?php

namespace App\Logic\Content\Branding\UseCase;

use App\Logic\Content\Branding\Manager\SiteBrandingManagerInterface;
use App\Logic\Content\Branding\Model\SiteBranding;

readonly class UpdateSiteBrandingUseCase
{
    public function __construct(private SiteBrandingManagerInterface $manager)
    {
    }

    /**
     * @param string|null $customLogoUrl null oder leer = Standardlogo
     */
    public function execute(?string $customLogoUrl): void
    {
        $this->manager->save(new SiteBranding($customLogoUrl));
    }
}
