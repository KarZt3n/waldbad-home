<?php

namespace App\Logic\Membership\Cancellation\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class MemberCancellationNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Die Kündigung "%s" wurde nicht gefunden.', $id));
    }
}
