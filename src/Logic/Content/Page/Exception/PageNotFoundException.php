<?php

namespace App\Logic\Content\Page\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

class PageNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $identifier)
    {
        parent::__construct(sprintf('Die Seite "%s" wurde nicht gefunden.', $identifier));
    }
}
