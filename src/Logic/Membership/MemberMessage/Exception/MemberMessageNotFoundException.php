<?php

namespace App\Logic\Membership\MemberMessage\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class MemberMessageNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Die Mitgliedernachricht "%s" wurde nicht gefunden.', $id));
    }
}
