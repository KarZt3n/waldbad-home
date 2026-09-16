<?php

namespace App\Logic\IdentityAccess\LoginToken\Exception;

use App\Logic\Common\Exception\UnauthenticatedException;

/**
 * Der Token existiert nicht (mehr), ist abgelaufen oder bereits eingelöst — bewusst eine einzige,
 * nichtssagende Meldung für alle drei Fälle (vgl. `InvalidMemberAccessTokenException`).
 */
final class InvalidLoginTokenException extends UnauthenticatedException
{
    public function __construct()
    {
        parent::__construct('Der Anmeldelink ist ungültig oder abgelaufen. Bitte fordere einen neuen an.');
    }
}
