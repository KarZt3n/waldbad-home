<?php

namespace App\Logic\Content\Page\Dto;

readonly class PageMediaReferenceResponse
{
    public function __construct(
        public string $mediaUrl,
        public string $pageId,
        public string $pageTitle,
        public bool $inDraft,
        public bool $inPublication,
    ) {
    }
}
