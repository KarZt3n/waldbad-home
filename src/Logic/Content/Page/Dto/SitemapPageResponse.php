<?php

namespace App\Logic\Content\Page\Dto;

readonly class SitemapPageResponse
{
    public function __construct(
        public string $slug,
        public \DateTimeImmutable $lastModified,
    ) {
    }
}
