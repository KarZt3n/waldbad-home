<?php

namespace App\UI\Site\Http;

use App\Logic\Content\Footer\Dto\FooterLinkResponse;
use App\Logic\Content\Footer\Query\GetPublicFooterLinksQuery;
use App\Logic\Content\Page\Query\GetNavigationQuery;
use App\Logic\Content\SocialMedia\Dto\SocialMediaLinkResponse;
use App\Logic\Content\SocialMedia\Query\GetPublicSocialMediaLinksQuery;

/**
 * Navigation, Footer und Social-Media-Leiste jeder öffentlichen Seite — gleiche Form für die
 * öffentlichen API-Endpunkte und die direkt ins HTML eingebetteten Daten (siehe `FrontendController`).
 */
readonly class PublicSiteChromeResponseFactory
{
    public function __construct(
        private GetNavigationQuery $navigation,
        private GetPublicFooterLinksQuery $footerLinks,
        private GetPublicSocialMediaLinksQuery $socialMediaLinks,
    ) {
    }

    /** @return list<array{id: string, label: string, slug: string, parentId: string|null}> */
    public function navigation(): array
    {
        return $this->navigation->execute();
    }

    /** @return list<array{label: string, slug: string}> */
    public function footerLinks(): array
    {
        return array_map(static fn (FooterLinkResponse $link): array => [
            'label' => $link->label,
            'slug' => $link->slug,
        ], $this->footerLinks->execute());
    }

    /** @return list<array{platform: string, label: string, url: string}> */
    public function socialMediaLinks(): array
    {
        return array_map(static fn (SocialMediaLinkResponse $link): array => [
            'platform' => $link->platform,
            'label' => $link->label,
            'url' => $link->url,
        ], $this->socialMediaLinks->execute());
    }
}
