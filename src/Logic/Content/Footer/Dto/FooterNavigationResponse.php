<?php

namespace App\Logic\Content\Footer\Dto;

readonly class FooterNavigationResponse
{
    /**
     * @param list<FooterPageResponse> $pages     Seiten im Footer, in Anzeigereihenfolge
     * @param list<FooterPageResponse> $available alle Seiten zur Auswahl
     */
    public function __construct(
        public array $pages,
        public array $available,
    ) {
    }
}
