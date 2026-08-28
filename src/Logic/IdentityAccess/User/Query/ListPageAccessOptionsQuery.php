<?php

namespace App\Logic\IdentityAccess\User\Query;

use App\Logic\IdentityAccess\User\Dto\PageAccessOption;
use App\Logic\IdentityAccess\User\PageAccessOptionProviderInterface;

readonly class ListPageAccessOptionsQuery
{
    public function __construct(private PageAccessOptionProviderInterface $provider)
    {
    }

    /**
     * @return list<PageAccessOption>
     */
    public function execute(): array
    {
        return $this->provider->findAll();
    }
}
