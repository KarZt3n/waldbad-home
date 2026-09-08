<?php

namespace App\Logic\Membership\Member\Dto;

final readonly class SageGsImportResponse
{
    /** @param list<ImportRowError> $errors */
    public function __construct(public int $created, public int $existing, public array $errors) {}
}
