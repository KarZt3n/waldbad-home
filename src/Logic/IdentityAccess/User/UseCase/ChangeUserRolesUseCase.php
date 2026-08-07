<?php

namespace App\Logic\IdentityAccess\User\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\IdentityAccess\User\Dto\UserResponse;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;
use App\Logic\IdentityAccess\User\Model\Role;

readonly class ChangeUserRolesUseCase
{
    public function __construct(
        private UserManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<Role> $roles
     */
    public function execute(string $id, array $roles): UserResponse
    {
        $user = $this->manager->get($id);

        if (
            in_array(Role::SuperAdmin, $user->roles, true)
            && !in_array(Role::SuperAdmin, $roles, true)
            && $this->manager->countActiveSuperAdmins() <= 1
        ) {
            throw new BusinessRuleViolationException('Dem letzten aktiven Super-Administrator kann die Rolle nicht entzogen werden.');
        }

        return UserResponse::fromUser($this->manager->save($user->changeRoles($roles, $this->clock->now())));
    }
}
