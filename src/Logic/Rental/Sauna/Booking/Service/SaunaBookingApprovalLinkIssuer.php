<?php

namespace App\Logic\Rental\Sauna\Booking\Service;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Common\SecureTokenGeneratorInterface;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingApprovalLinks;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingApprovalTokenManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingApprovalToken;
use App\Logic\Rental\Sauna\Booking\SaunaBookingApprovalLinkBuilderInterface;

/**
 * Stellt für die Benachrichtigung über eine neue Anfrage die Links „Annehmen“ und „Ablehnen“ aus — beide
 * mit demselben, einmalig verwendbaren Token — sowie den Link zur Übersicht in der Redaktion.
 */
readonly class SaunaBookingApprovalLinkIssuer
{
    public const int VALIDITY_DAYS = 14;

    public function __construct(
        private SaunaBookingApprovalTokenManagerInterface $tokens,
        private SecureTokenGeneratorInterface $tokenGenerator,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private SaunaBookingApprovalLinkBuilderInterface $linkBuilder,
    ) {
    }

    /** @param non-empty-list<SaunaBooking> $bookings */
    public function issue(array $bookings): SaunaBookingApprovalLinks
    {
        $rawToken = $this->tokenGenerator->generate();
        $this->tokens->save(new SaunaBookingApprovalToken(
            id: $this->identifierGenerator->generate(),
            tokenHash: hash('sha256', $rawToken),
            bookingIds: array_map(static fn (SaunaBooking $booking): string => $booking->id, $bookings),
            expiresAt: $this->clock->now()->modify('+'.self::VALIDITY_DAYS.' days'),
        ));

        return new SaunaBookingApprovalLinks(
            $this->linkBuilder->approval($rawToken),
            $this->linkBuilder->rejection($rawToken),
            $this->linkBuilder->overview(),
        );
    }
}
