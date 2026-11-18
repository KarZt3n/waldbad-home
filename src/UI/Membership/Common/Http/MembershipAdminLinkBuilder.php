<?php

namespace App\UI\Membership\Common\Http;

use App\Logic\Membership\MembershipAdminLinkBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `/admin/mitglieder/{Reiter}` — öffnet die Mitgliederverwaltung direkt auf dem Reiter (siehe
 * `membershipTabsBySlug` in `assets/admin/members.js`).
 */
readonly class MembershipAdminLinkBuilder implements MembershipAdminLinkBuilderInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function applications(): string
    {
        return $this->tab('antraege');
    }

    public function messages(): string
    {
        return $this->tab('nachrichten');
    }

    public function cancellations(): string
    {
        return $this->tab('kuendigungen');
    }

    private function tab(string $slug): string
    {
        return $this->urlGenerator->generate('admin_app', ['path' => 'mitglieder/'.$slug], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
