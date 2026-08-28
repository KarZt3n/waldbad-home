<?php

namespace App\Logic\Event\Schedule\Dto;

readonly class ImportEventContentBlocksResult
{
    /**
     * @param list<string> $imported Titel der neu angelegten Veranstaltungen.
     * @param list<string> $skipped Titel der bereits vorhandenen (übersprungenen) Veranstaltungen.
     */
    public function __construct(
        public array $imported,
        public array $skipped,
    ) {
    }
}
