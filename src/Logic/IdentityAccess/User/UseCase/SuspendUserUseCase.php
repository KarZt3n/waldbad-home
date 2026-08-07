<?php

namespace App\Logic\IdentityAccess\User\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\IdentityAccess\User\Dto\UserResponse;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;
use App\Logic\IdentityAccess\User\Model\Role;

readonly class SuspendUserUseCase
{
    public function __construct(
        private UserManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id): UserResponse
    {
        $user = $this->manager->get($id);
        if (in_array(Role::SuperAdmin, $user->roles, true) && $this->manager->countActiveSuperAdmins() <= 1) {
            throw new BusinessRuleViolationException('Der letzte aktive Super-Administrator kann nicht gesperrt werden.');
        }

        return UserResponse::fromUser($this->manager->save($user->suspend($this->clock->now())));
    }
}
