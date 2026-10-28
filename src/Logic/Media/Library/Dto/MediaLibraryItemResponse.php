<?php

namespace App\Logic\Media\Library\Dto;

use App\Logic\Media\Library\Model\MediaFile;
use App\Logic\Media\Library\Model\MediaUsage;

readonly class MediaLibraryItemResponse
{
    /**
     * @param list<MediaUsage> $usages
     */
    public function __construct(
        public MediaFile $file,
        public array $usages,
    ) {
    }
}
