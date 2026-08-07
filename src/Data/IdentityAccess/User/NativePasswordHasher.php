<?php

namespace App\Data\IdentityAccess\User;

use App\Logic\IdentityAccess\User\PasswordHasherInterface;

readonly class NativePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_DEFAULT);
    }
}
