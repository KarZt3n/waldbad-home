<?php

namespace App\Logic\Membership\MemberAccess\Exception;

use App\Logic\Common\Exception\AccessDeniedException;

/**
 * Der Token existiert nicht (mehr), ist abgelaufen, oder es gibt keine Mitgliedsdatensätze mehr zur
 * hinterlegten E-Mail-Adresse — bewusst eine einzige, nichtssagende Meldung für alle drei Fälle,
 * damit niemand über die Fehlermeldung herausfinden kann, ob eine E-Mail-Adresse als Mitglied
 * existiert.
 */
final class InvalidMemberAccessTokenException extends AccessDeniedException
{
    public function __construct()
    {
        parent::__construct('Der Zugangslink ist ungültig oder abgelaufen. Bitte fordere einen neuen an.');
    }
}
