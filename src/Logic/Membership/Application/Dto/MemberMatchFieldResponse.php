<?php

namespace App\Logic\Membership\Application\Dto;

readonly class MemberMatchFieldResponse
{
    public const string IDENTICAL = 'identical';
    public const string CHANGED = 'changed';
    /** Im Antrag leer: der bestehende Wert bleibt bei einer Übernahme erhalten. */
    public const string KEPT = 'kept';
    /** Neue Person: es gibt keinen bestehenden Wert. */
    public const string NEW = 'new';

    public function __construct(
        public string $key,
        public string $label,
        public ?string $applicationValue,
        public ?string $memberValue,
        public string $status,
    ) {
    }
}
