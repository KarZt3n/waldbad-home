<?php

namespace App\Logic\IdentityAccess\Session\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Kurzlebiger Berechtigungsnachweis (siehe `App\UI\IdentityAccess\Security\AccessTokenAuthenticator`)
 * für die laufende Redaktions-Sitzung — wird bei jedem Aufruf einer geschützten Route erneut
 * geprüft. Immer zusammen mit einem `RefreshToken` ausgestellt (siehe `SessionTokenIssuer`).
 *
 * Wie bei `App\Logic\IdentityAccess\LoginToken\Model\LoginToken` wird nur der SHA-256-Hash
 * gespeichert, nie der Token selbst.
 */
readonly class AccessToken
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $tokenHash,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
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
        return $at >= $this->expiresAt;
    }
}
