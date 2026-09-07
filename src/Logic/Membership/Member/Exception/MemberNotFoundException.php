<?php

namespace App\Logic\Membership\Member\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class MemberNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Das Mitglied "%s" wurde nicht gefunden.', $id));
    }
}
