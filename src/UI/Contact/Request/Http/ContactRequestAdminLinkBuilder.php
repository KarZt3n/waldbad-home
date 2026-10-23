<?php

namespace App\UI\Contact\Request\Http;

use App\Logic\Contact\Request\ContactRequestAdminLinkBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `/admin/kontakt-feedback/kontaktanfragen/{Anfrage}` — die Redaktion öffnet dort den Reiter
 * „Kontaktanfrage“ und springt zur Anfrage (siehe `showContactFeedbackManagement` in
 * `assets/admin/contact.js`).
 */
readonly class ContactRequestAdminLinkBuilder implements ContactRequestAdminLinkBuilderInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function build(string $contactRequestId): string
    {
        return $this->urlGenerator->generate(
            'admin_app',
            ['path' => 'kontakt-feedback/kontaktanfragen/'.rawurlencode($contactRequestId)],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
