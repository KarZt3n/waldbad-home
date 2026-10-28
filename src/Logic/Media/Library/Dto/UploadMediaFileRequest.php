<?php

namespace App\Logic\Media\Library\Dto;

use App\Logic\Media\Library\Model\MediaUpload;

readonly class UploadMediaFileRequest
{
    public function __construct(
        public string $folderPath,
        public MediaUpload $upload,
    ) {
    }
}
