<?php

namespace App\Logic\IdentityAccess\Session\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\IdentityAccess\Session\Manager\AccessTokenManagerInterface;
use App\Logic\IdentityAccess\Session\Manager\RefreshTokenManagerInterface;
use App\Logic\IdentityAccess\Session\Model\LoginSessionEndReason;
use App\Logic\IdentityAccess\Session\Service\SessionTokenIssuer;

/**
 * Beendet die Redaktions-Sitzung: löscht/sperrt Access- und Refresh-Token, sofern (noch) vorhanden,
 * und hält das Ende in der Sitzungshistorie fest (Abmeldung oder Inaktivitäts-Logout).
 * Wirft absichtlich nie einen Fehler — aus Sicht der aufrufenden Controller-Action (und des
 * Frontends) ist ein Logout immer „erfolgreich", auch wenn die Tokens bereits abgelaufen oder gar
 * nicht mehr vorhanden waren.
 */
readonly class LogoutUseCase
{
    public function __construct(
        private AccessTokenManagerInterface $accessTokens,
        private RefreshTokenManagerInterface $refreshTokens,
        private SessionTokenIssuer $issuer,
        private ClockInterface $clock,
    ) {
    }

    public function execute(
        ?string $rawAccessToken,
        ?string $rawRefreshToken,
        LoginSessionEndReason $reason = LoginSessionEndReason::Logout,
    ): void {
        if ($rawAccessToken !== null) {
            $token = $this->accessTokens->findByHash(hash('sha256', $rawAccessToken));
            if ($token !== null) {
                $this->accessTokens->delete($token->id);
            }
        }

        if ($rawRefreshToken !== null) {
            $token = $this->refreshTokens->findByHash(hash('sha256', $rawRefreshToken));
            if ($token !== null) {
                if (!$token->isRevoked()) {
                    $this->refreshTokens->revoke($token->id, $this->clock->now());
                }
                $this->issuer->endSession($token, $reason);
            }
        }
    }
}
