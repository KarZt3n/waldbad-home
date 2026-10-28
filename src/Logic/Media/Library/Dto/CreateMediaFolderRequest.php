<?php

namespace App\Logic\Media\Library\Dto;

readonly class CreateMediaFolderRequest
{
    public function __construct(
        public string $parentPath,
        public string $name,
    ) {
    }
}
