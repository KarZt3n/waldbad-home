<?php

namespace App\Logic\Membership\Application\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class MembershipApplicationNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Der Mitgliedsantrag "%s" wurde nicht gefunden.', $id));
    }
}
