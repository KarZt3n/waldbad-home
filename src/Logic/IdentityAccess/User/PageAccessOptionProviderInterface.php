<?php

namespace App\Logic\IdentityAccess\User;

use App\Logic\IdentityAccess\User\Dto\PageAccessOption;

interface PageAccessOptionProviderInterface
{
    /**
     * @return list<PageAccessOption>
     */
    public function findAll(): array;
}
