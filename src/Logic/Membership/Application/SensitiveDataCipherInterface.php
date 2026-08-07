<?php

namespace App\Logic\Membership\Application;

interface SensitiveDataCipherInterface
{
    public function encrypt(string $plainText): string;

    public function decrypt(string $cipherText): string;
}
