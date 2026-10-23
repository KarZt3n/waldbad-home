<?php

namespace App\UI\Guestbook\Entry\Http;

use App\Logic\Guestbook\Entry\GuestbookEntryAdminLinkBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `/admin/kontakt-feedback/gaestebuch/{Eintrag}` — die Redaktion öffnet dort den Reiter
 * „Gästebuch“ und springt zum Eintrag (siehe `showContactFeedbackManagement` in
 * `assets/admin/contact.js`).
 */
readonly class GuestbookEntryAdminLinkBuilder implements GuestbookEntryAdminLinkBuilderInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function build(string $entryId): string
    {
        return $this->urlGenerator->generate(
            'admin_app',
            ['path' => 'kontakt-feedback/gaestebuch/'.rawurlencode($entryId)],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
