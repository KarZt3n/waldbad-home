<?php

namespace App\Logic\Media\Library\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class MediaFolderNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $path)
    {
        parent::__construct(sprintf('Der Ordner "/uploads/%s" wurde nicht gefunden.', $path));
    }
}
