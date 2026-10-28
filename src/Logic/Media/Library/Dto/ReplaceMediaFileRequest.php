<?php

namespace App\Logic\Media\Library\Dto;

use App\Logic\Media\Library\Model\MediaUpload;

readonly class ReplaceMediaFileRequest
{
    public function __construct(
        public string $url,
        public MediaUpload $upload,
    ) {
    }
}
