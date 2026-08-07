<?php

namespace App\Logic\IdentityAccess\User;

interface PasswordHasherInterface
{
    public function hash(string $plainPassword): string;
}
