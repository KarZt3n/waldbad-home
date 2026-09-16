<?php

namespace App\Logic\IdentityAccess\LoginToken\Event;

readonly class LoginRequestedEvent
{
    public function __construct(public string $email)
    {
    }
}
