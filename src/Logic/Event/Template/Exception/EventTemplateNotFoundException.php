<?php

namespace App\Logic\Event\Template\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class EventTemplateNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Die Vorlage "%s" wurde nicht gefunden.', $id));
    }
}
