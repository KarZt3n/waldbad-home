<?php

namespace App\Logic\Media\Library\Model;

readonly class MediaUsage
{
    public function __construct(
        public string $url,
        public MediaUsageArea $area,
        public string $referenceId,
        public string $title,
        public ?string $detail = null,
    ) {
    }
}
