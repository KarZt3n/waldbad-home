<?php

namespace App\Logic\Media\PhotoAlbum\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class PhotoAlbumNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Der Foto-Eintrag "%s" wurde nicht gefunden.', $id));
    }
}
