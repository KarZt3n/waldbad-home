<?php

namespace App\Logic\IdentityAccess\Session\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\IdentityAccess\LoginToken\Exception\InvalidLoginTokenException;
use App\Logic\IdentityAccess\LoginToken\Manager\LoginTokenManagerInterface;
use App\Logic\IdentityAccess\Session\Dto\AuthenticatedSession;
use App\Logic\IdentityAccess\Session\Service\SessionTokenIssuer;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;

/**
 * Löst einen per Mail verschickten Login-Token (siehe `App\Logic\IdentityAccess\LoginToken\Model\LoginToken`)
 * in eine neue Redaktions-Sitzung auf — Access- und Refresh-Token werden hier zum ersten Mal für
 * diesen Login ausgestellt (siehe `SessionTokenIssuer::issueInitial()`), der Login-Token wird dabei
 * verbraucht (kann kein zweites Mal eingelöst werden).
 */
readonly class RedeemLoginTokenUseCase
{
    public function __construct(
        private LoginTokenManagerInterface $loginTokens,
        private UserManagerInterface $users,
        private SessionTokenIssuer $issuer,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $rawToken): AuthenticatedSession
    {
        $now = $this->clock->now();
        $token = $this->loginTokens->findByHash(hash('sha256', $rawToken)) ?? throw new InvalidLoginTokenException();
        if ($token->isExpired($now) || $token->isConsumed()) {
            throw new InvalidLoginTokenException();
        }

        $user = $this->users->findByEmail($token->email);
        if ($user === null || !$user->active) {
            throw new InvalidLoginTokenException();
        }

        $this->loginTokens->markConsumed($token->id, $now);
        $user = $this->users->save($user->recordLogin($now));

        return new AuthenticatedSession($user, $this->issuer->issueInitial($user->id));
    }
}
