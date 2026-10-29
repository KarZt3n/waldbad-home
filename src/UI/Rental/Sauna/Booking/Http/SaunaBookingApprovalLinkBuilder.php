<?php

namespace App\UI\Rental\Sauna\Booking\Http;

use App\Logic\Rental\Sauna\Booking\SaunaBookingApprovalLinkBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class SaunaBookingApprovalLinkBuilder implements SaunaBookingApprovalLinkBuilderInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function approval(string $rawToken): string
    {
        return $this->urlGenerator->generate('public_sauna_booking_approval', ['token' => $rawToken], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function rejection(string $rawToken): string
    {
        return $this->urlGenerator->generate('public_sauna_booking_approval', ['token' => $rawToken, 'aktion' => 'ablehnen'], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /** `/admin/vermietung/sauna/anmeldungen` (siehe `RENTAL_SUBMODULES` in `assets/admin/rental.js`). */
    public function overview(): string
    {
        return $this->urlGenerator->generate('admin_app', ['path' => 'vermietung/sauna/anmeldungen'], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
