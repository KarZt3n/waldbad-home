<?php

namespace App\Logic\IdentityAccess\User\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\IdentityAccess\User\Dto\UserResponse;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\Role;

readonly class ChangeUserAccessUseCase
{
    public function __construct(
        private UserManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<Role> $roles
     * @param list<ModuleAccess> $moduleAccess
     * @param list<Role> $actorRoles
     */
    public function execute(string $id, array $roles, array $moduleAccess, array $actorRoles): UserResponse
    {
        $user = $this->manager->get($id);
        $actorIsSuperAdmin = in_array(Role::SuperAdmin, $actorRoles, true);
        $actorIsAdmin = $actorIsSuperAdmin || in_array(Role::Admin, $actorRoles, true);

        if (in_array(Role::SuperAdmin, $user->roles, true) && !$actorIsSuperAdmin) {
            throw new BusinessRuleViolationException('Super-Administratoren dürfen ausschließlich von einem Super-Administrator bearbeitet werden.');
        }

        if (in_array(Role::Admin, $user->roles, true) && !$actorIsAdmin) {
            throw new BusinessRuleViolationException('Administratoren dürfen ausschließlich von einem Administrator bearbeitet werden.');
        }

        if (in_array(Role::SuperAdmin, $roles, true) && !$actorIsSuperAdmin) {
            throw new BusinessRuleViolationException('Nur ein Super-Administrator darf die Rolle SuperAdmin vergeben.');
        }

        if (in_array(Role::Admin, $roles, true) && !$actorIsAdmin) {
            throw new BusinessRuleViolationException('Nur Administratoren dürfen die Rolle Admin vergeben.');
        }

        if (
            in_array(Role::SuperAdmin, $user->roles, true)
            && !in_array(Role::SuperAdmin, $roles, true)
            && $this->manager->countActiveSuperAdmins() <= 1
        ) {
            throw new BusinessRuleViolationException('Dem letzten aktiven Super-Administrator kann die Rolle nicht entzogen werden.');
        }

        return UserResponse::fromUser($this->manager->save($user->changeAccess($roles, $moduleAccess, $this->clock->now())));
    }
}
