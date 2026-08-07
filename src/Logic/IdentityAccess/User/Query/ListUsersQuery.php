<?php

namespace App\Logic\IdentityAccess\User\Query;

use App\Logic\IdentityAccess\User\Dto\UserResponse;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;

readonly class ListUsersQuery
{
    public function __construct(private UserManagerInterface $manager)
    {
    }

    /**
     * @return list<UserResponse>
     */
    public function execute(): array
    {
        return array_map(UserResponse::fromUser(...), $this->manager->all());
    }
}
