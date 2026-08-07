<?php

namespace App\UI\IdentityAccess\Security;

use App\Logic\Common\Exception\ResourceNotFoundException;
use App\Logic\IdentityAccess\Authentication\Query\GetAuthenticationIdentityQuery;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<AuthenticatedUser>
 */
readonly class AuthenticatedUserProvider implements UserProviderInterface
{
    public function __construct(private GetAuthenticationIdentityQuery $query)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $identity = $this->query->execute($identifier);
        } catch (ResourceNotFoundException) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        if (!$identity->active) {
            $exception = new UserNotFoundException('Das Benutzerkonto ist gesperrt.');
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        return new AuthenticatedUser($identity);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === AuthenticatedUser::class;
    }
}
