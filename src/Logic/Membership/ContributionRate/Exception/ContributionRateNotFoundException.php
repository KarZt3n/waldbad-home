<?php

namespace App\Logic\Membership\ContributionRate\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class ContributionRateNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Der Beitragssatz "%s" wurde nicht gefunden.', $id));
    }
}
