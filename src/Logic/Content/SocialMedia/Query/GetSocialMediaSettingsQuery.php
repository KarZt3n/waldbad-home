<?php

namespace App\Logic\Content\SocialMedia\Query;

use App\Logic\Content\SocialMedia\Dto\SocialMediaLinkResponse;
use App\Logic\Content\SocialMedia\Dto\SocialMediaSettingsResponse;
use App\Logic\Content\SocialMedia\Manager\SocialMediaSettingsManagerInterface;
use App\Logic\Content\SocialMedia\Model\SocialMediaLink;

readonly class GetSocialMediaSettingsQuery
{
    public function __construct(private SocialMediaSettingsManagerInterface $manager)
    {
    }

    public function execute(): SocialMediaSettingsResponse
    {
        $settings = $this->manager->get();

        return new SocialMediaSettingsResponse(
            visible: $settings->visible,
            links: array_map(
                static fn (SocialMediaLink $link): SocialMediaLinkResponse => new SocialMediaLinkResponse($link->platform->value, $link->platform->label(), $link->url),
                $settings->links,
            ),
        );
    }
}
