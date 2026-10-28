<?php

namespace App\Logic\Overview\Dto;

use App\Logic\IdentityAccess\User\Model\CmsModule;

readonly class GetOverviewRequest
{
    /**
     * @param list<CmsModule> $modules Module, die die angemeldete Person sehen darf
     */
    public function __construct(public array $modules)
    {
    }
}
