<?php

namespace App\Logic\Media\Library\Dto;

use App\Logic\Media\Library\Model\MediaFile;

readonly class MoveMediaFileResponse
{
    public function __construct(
        public MediaFile $file,
        public int $updatedReferences,
    ) {
    }
}
