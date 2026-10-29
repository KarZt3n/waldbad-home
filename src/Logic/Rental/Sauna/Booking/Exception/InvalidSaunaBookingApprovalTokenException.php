<?php

namespace App\Logic\Rental\Sauna\Booking\Exception;

use App\Logic\Common\Exception\UnauthenticatedException;

/**
 * Der Link existiert nicht, ist abgelaufen oder wurde schon benutzt — bewusst eine einzige Meldung
 * für alle Fälle (vgl. `InvalidMemberEmailConsentTokenException`).
 */
final class InvalidSaunaBookingApprovalTokenException extends UnauthenticatedException
{
    public function __construct()
    {
        parent::__construct('Der Link ist ungültig, abgelaufen oder wurde bereits benutzt. Die Anfrage kann in der Redaktion unter „Vermietung“ → „Sauna“ → „Anmeldungen“ bearbeitet werden.');
    }
}
