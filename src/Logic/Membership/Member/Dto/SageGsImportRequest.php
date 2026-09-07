<?php

namespace App\Logic\Membership\Member\Dto;

final readonly class SageGsImportRequest
{
    /** @param array<int, CreateMemberRequest> $rows Originale, einsbasierte Datensatzpositionen. */
    public function __construct(public array $rows, public bool $execute = false) {}
}
