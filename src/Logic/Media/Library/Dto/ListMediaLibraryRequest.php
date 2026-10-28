<?php

namespace App\Logic\Media\Library\Dto;

readonly class ListMediaLibraryRequest
{
    public function __construct(
        public string $folderPath,
        public bool $recursive,
    ) {
    }
}
