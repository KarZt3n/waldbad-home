<?php

namespace App\Logic\Media\Library\Dto;

use App\Logic\Media\Library\Model\MediaFile;

readonly class ReplaceMediaFileResponse
{
    public function __construct(
        public MediaFile $file,
        public int $updatedReferences,
    ) {
    }
}
