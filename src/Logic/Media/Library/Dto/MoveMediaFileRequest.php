<?php

namespace App\Logic\Media\Library\Dto;

readonly class MoveMediaFileRequest
{
    public function __construct(
        public string $url,
        public string $targetFolderPath,
    ) {
    }
}
