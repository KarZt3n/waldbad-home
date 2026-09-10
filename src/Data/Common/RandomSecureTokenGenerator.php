<?php

namespace App\Data\Common;

use App\Logic\Common\SecureTokenGeneratorInterface;

readonly class RandomSecureTokenGenerator implements SecureTokenGeneratorInterface
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
