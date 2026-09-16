<?php

namespace App\Logic\IdentityAccess\Session\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Löst bei Bedarf ein neues `AccessToken` (+ ein neues `RefreshToken`) ein — siehe
 * `SessionTokenIssuer`, `RefreshSessionUseCase`. Gleitend gültig (`$expiresAt`, verlängert sich mit
 * jeder Nutzung um dieselbe Spanne erneut), aber gedeckelt durch `$absoluteExpiresAt`
 * (unveränderlich seit der Erstanmeldung, siehe `RedeemLoginTokenUseCase`) — auch bei
 * durchgehender Aktivität endet die Sitzung spätestens dort und verlangt eine erneute Anmeldung
 * per Magic Link.
 *
 * Wird bei jeder Nutzung rotiert: das bisherige Token wird invalidiert (`$revokedAt`), nicht
 * gelöscht — eine erneute Vorlage eines bereits rotierten Tokens deutet auf einen gestohlenen
 * Token hin und lässt `RefreshSessionUseCase` vorsorglich alle Refresh-Tokens des Benutzers sperren
 * (außer innerhalb einer kurzen Gnadenfrist nach der Rotation, siehe dort).
 */
readonly class RefreshToken
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $tokenHash,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $absoluteExpiresAt,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $revokedAt = null,
    ) {
        if (trim($this->userId) === '') {
            throw new BusinessRuleViolationException('Die Benutzer-ID ist erforderlich.');
        }
        if (trim($this->tokenHash) === '') {
            throw new BusinessRuleViolationException('Der Token-Hash ist erforderlich.');
        }
    }

    public function isExpired(\DateTimeImmutable $at): bool
    {
        return $at >= $this->expiresAt || $at >= $this->absoluteExpiresAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }
}
