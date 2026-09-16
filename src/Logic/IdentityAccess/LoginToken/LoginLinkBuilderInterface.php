<?php

namespace App\Logic\IdentityAccess\LoginToken;

interface LoginLinkBuilderInterface
{
    public function build(string $token): string;
}
