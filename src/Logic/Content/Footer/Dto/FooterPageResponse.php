<?php

namespace App\Logic\Content\Footer\Dto;

readonly class FooterPageResponse
{
    public function __construct(
        public string $id,
        public string $label,
        public string $slug,
        /** Veröffentlicht und sichtbar, erscheint also tatsächlich im Footer der Website. */
        public bool $online,
    ) {
    }
}
