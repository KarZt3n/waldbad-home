<?php

namespace App\Logic\Common\Exception;

/**
 * Für Domain-Exceptions, deren Fehler die Oberfläche einem bestimmten Teil der Eingabe zuordnen
 * soll (z. B. einem von mehreren Wunschtagen) — `DomainExceptionSubscriber` gibt die Angaben als
 * `error.details` mit aus.
 */
interface ErrorDetailsInterface
{
    /**
     * @return array<string, list<int>>
     */
    public function details(): array;
}
