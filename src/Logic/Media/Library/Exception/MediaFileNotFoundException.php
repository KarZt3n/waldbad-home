<?php

namespace App\Logic\Media\Library\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class MediaFileNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $url)
    {
        parent::__construct(sprintf('Die Datei "%s" wurde in der Mediathek nicht gefunden.', $url));
    }
}
