<?php

namespace App\Logic\IdentityAccess\User\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Dto\UserResponse;
use App\Logic\IdentityAccess\User\Exception\UserEmailAlreadyExistsException;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;
use App\Logic\IdentityAccess\User\Model\User;
use App\Logic\IdentityAccess\User\PasswordHasherInterface;

readonly class CreateUserUseCase
{
    public function __construct(
        private UserManagerInterface $manager,
        private PasswordHasherInterface $passwordHasher,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(CreateUserRequest $request): UserResponse
    {
        $email = mb_strtolower(trim($request->email));
        if ($this->manager->findByEmail($email) !== null) {
            throw new UserEmailAlreadyExistsException();
        }

        if (mb_strlen($request->plainPassword) < 12) {
            throw new BusinessRuleViolationException('Das Passwort muss mindestens 12 Zeichen lang sein.');
        }

        $now = $this->clock->now();
        $user = new User(
            id: $this->identifierGenerator->generate(),
            email: $email,
            displayName: trim($request->displayName),
            passwordHash: $this->passwordHasher->hash($request->plainPassword),
            roles: $request->roles,
            moduleAccess: $request->moduleAccess,
            active: true,
            version: 0,
            createdAt: $now,
            updatedAt: $now,
            lastLoginAt: null,
        );

        return UserResponse::fromUser($this->manager->save($user));
    }
}
