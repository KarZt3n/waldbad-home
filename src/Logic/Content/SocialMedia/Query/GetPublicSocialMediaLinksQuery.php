<?php

namespace App\Logic\Content\SocialMedia\Query;

use App\Logic\Content\SocialMedia\Dto\SocialMediaLinkResponse;
use App\Logic\Content\SocialMedia\Manager\SocialMediaSettingsManagerInterface;
use App\Logic\Content\SocialMedia\Model\SocialMediaLink;

readonly class GetPublicSocialMediaLinksQuery
{
    public function __construct(private SocialMediaSettingsManagerInterface $manager)
    {
    }

    /**
     * @return list<SocialMediaLinkResponse> leer, solange die Leiste ausgeblendet ist
     */
    public function execute(): array
    {
        return array_map(
            static fn (SocialMediaLink $link): SocialMediaLinkResponse => new SocialMediaLinkResponse($link->platform->value, $link->platform->label(), $link->url),
            $this->manager->get()->publicLinks(),
        );
    }
}
