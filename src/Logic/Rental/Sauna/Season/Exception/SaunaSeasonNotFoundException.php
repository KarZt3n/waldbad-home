<?php

namespace App\Logic\Rental\Sauna\Season\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class SaunaSeasonNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Die Sauna-Saison "%s" wurde nicht gefunden.', $id));
    }
}
