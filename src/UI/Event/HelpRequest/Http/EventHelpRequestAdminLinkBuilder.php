<?php

namespace App\UI\Event\HelpRequest\Http;

use App\Logic\Event\HelpRequest\EventHelpRequestAdminLinkBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `/admin/veranstaltungen/helfer/{Veranstaltung}` — die Redaktion öffnet dort den Reiter
 * „Veranstaltungshelfer“ und springt zur Veranstaltung (siehe `showEventManagement` in
 * `assets/admin/events.js`).
 */
readonly class EventHelpRequestAdminLinkBuilder implements EventHelpRequestAdminLinkBuilderInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function build(string $eventIdentifier): string
    {
        return $this->urlGenerator->generate(
            'admin_app',
            ['path' => 'veranstaltungen/helfer/'.rawurlencode($eventIdentifier)],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
