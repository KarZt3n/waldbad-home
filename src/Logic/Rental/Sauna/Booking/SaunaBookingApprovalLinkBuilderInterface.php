<?php

namespace App\Logic\Rental\Sauna\Booking;

/** Baut die absoluten Links der Benachrichtigung über eine neue Sauna-Anfrage (UI-Routing). */
interface SaunaBookingApprovalLinkBuilderInterface
{
    /** Seite „Sauna-Anfrage annehmen“ ohne Anmeldung, berechtigt durch den Token. */
    public function approval(string $rawToken): string;

    /** Dieselbe Seite im Modus „ablehnen“. */
    public function rejection(string $rawToken): string;

    /** Liste aller Anmeldungen in der Redaktion (mit Anmeldung). */
    public function overview(): string;
}
