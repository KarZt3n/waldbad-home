<?php

namespace App\UI\IdentityAccess\Security;

use App\Logic\Common\ClockInterface;
use App\Logic\IdentityAccess\Session\Manager\AccessTokenManagerInterface;
use App\Logic\IdentityAccess\User\Exception\UserNotFoundException;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Validiert den per `CookieAccessTokenExtractor` gelesenen Access-Token bei jedem Request neu
 * (kein serverseitiger Sitzungszustand jenseits der DB-Zeile, siehe
 * `App\Logic\IdentityAccess\Session\Model\AccessToken`) — dadurch wirkt eine Benutzersperrung
 * (`SuspendUserUseCase`) sofort: `AuthenticatedUserProvider::loadUserByIdentifier()` (über den
 * konfigurierten `provider: cms_users`, siehe `config/packages/security.yaml`) prüft den
 * Aktiv-Status bei jedem Aufruf erneut.
 */
readonly class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private AccessTokenManagerInterface $accessTokens,
        private UserManagerInterface $users,
        private ClockInterface $clock,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->accessTokens->findByHash(hash('sha256', $accessToken));
        if ($token === null || $token->isExpired($this->clock->now())) {
            throw new CustomUserMessageAuthenticationException('Die Anmeldung ist abgelaufen.');
        }

        try {
            $email = $this->users->get($token->userId)->email;
        } catch (UserNotFoundException) {
            throw new CustomUserMessageAuthenticationException('Die Anmeldung ist ungültig.');
        }

        return new UserBadge($email);
    }
}
