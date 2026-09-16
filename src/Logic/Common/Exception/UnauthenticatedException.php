<?php

namespace App\Logic\Common\Exception;

/**
 * Es besteht (noch) keine gültige Identität — anders als `AccessDeniedException` (eine bekannte
 * Identität besitzt nicht die nötige Berechtigung). Beispiele: ein abgelaufener/bereits
 * eingelöster Login-Token, ein ungültiger Refresh-Token (siehe `RedeemLoginTokenUseCase`,
 * `RefreshSessionUseCase`).
 */
class UnauthenticatedException extends DomainException
{
}
