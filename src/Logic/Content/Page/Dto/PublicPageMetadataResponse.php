<?php

namespace App\Logic\Content\Page\Dto;

/** Titel und Beschreibung einer öffentlichen Seite für den serverseitig ausgelieferten `<head>`. */
readonly class PublicPageMetadataResponse
{
    public function __construct(
        public string $title,
        public ?string $description,
    ) {
    }
}
