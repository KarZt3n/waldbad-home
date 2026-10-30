<?php

namespace App\Logic\Content\SocialMedia\UseCase;

use App\Logic\Content\SocialMedia\Dto\SocialMediaLinkRequest;
use App\Logic\Content\SocialMedia\Dto\UpdateSocialMediaSettingsRequest;
use App\Logic\Content\SocialMedia\Manager\SocialMediaSettingsManagerInterface;
use App\Logic\Content\SocialMedia\Model\SocialMediaLink;
use App\Logic\Content\SocialMedia\Model\SocialMediaSettings;

readonly class UpdateSocialMediaSettingsUseCase
{
    public function __construct(private SocialMediaSettingsManagerInterface $manager)
    {
    }

    public function execute(UpdateSocialMediaSettingsRequest $request): void
    {
        $this->manager->save(new SocialMediaSettings(
            visible: $request->visible,
            links: array_map(
                static fn (SocialMediaLinkRequest $link): SocialMediaLink => new SocialMediaLink($link->platform, $link->url),
                $request->links,
            ),
        ));
    }
}
