<?php

namespace App\Logic\Common;

/**
 * Serverseitiger Read-Cache für Business-Models (Cache-Aside im jeweiligen Manager, siehe
 * `architektur-patterns.md`). Der Manager prüft beim Laden selbst, ob der gecachte Wert noch zum
 * erwarteten Modell passt, und invalidiert seine Schlüssel nach jedem Schreiben.
 */
interface ModelCacheInterface
{
    /**
     * `null`, wenn unter dem Schlüssel (noch) nichts gecacht ist. Der Inhalt stammt aus dem Speicher
     * und ist deshalb ungeprüft — der Manager prüft ihn gegen das erwartete Modell.
     *
     * @return object|array<mixed>|null
     */
    public function load(string $key): object|array|null;

    /** @param object|array<mixed> $value */
    public function store(string $key, object|array $value): void;

    public function invalidate(string $key): void;
}
