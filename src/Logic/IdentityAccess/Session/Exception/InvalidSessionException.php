<?php

namespace App\Logic\IdentityAccess\Session\Exception;

use App\Logic\Common\Exception\UnauthenticatedException;

/**
 * Der Refresh-Token existiert nicht (mehr), ist abgelaufen, bereits rotiert/gesperrt, oder der
 * zugehörige Benutzer ist inzwischen inaktiv — bewusst eine einzige, nichtssagende Meldung für alle
 * Fälle (siehe `RefreshSessionUseCase`).
 */
final class InvalidSessionException extends UnauthenticatedException
{
    public function __construct()
    {
        parent::__construct('Die Sitzung ist abgelaufen. Bitte melde dich erneut an.');
    }
}
