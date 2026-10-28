<?php

namespace App\Logic\Media\Library\Dto;

readonly class RenameMediaFileRequest
{
    public function __construct(
        public string $url,
        /** Neuer Dateiname ohne Endung; die Endung bleibt erhalten. */
        public string $filename,
    ) {
    }
}
