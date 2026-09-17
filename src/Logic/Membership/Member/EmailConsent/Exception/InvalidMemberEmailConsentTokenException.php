<?php

namespace App\Logic\Membership\Member\EmailConsent\Exception;

use App\Logic\Common\Exception\UnauthenticatedException;

/**
 * Der Bestätigungslink existiert nicht (mehr), ist abgelaufen oder bereits eingelöst — bewusst eine
 * einzige, nichtssagende Meldung für alle drei Fälle (vgl. `InvalidLoginTokenException`).
 */
final class InvalidMemberEmailConsentTokenException extends UnauthenticatedException
{
    public function __construct()
    {
        parent::__construct('Der Bestätigungslink ist ungültig oder abgelaufen. Bitte im Verein einen neuen anfordern lassen.');
    }
}
