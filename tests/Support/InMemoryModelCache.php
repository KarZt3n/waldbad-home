<?php

namespace App\Tests\Support;

use App\Logic\Common\ModelCacheInterface;

final class InMemoryModelCache implements ModelCacheInterface
{
    /** @var array<string, object|array<mixed>> */
    private array $values = [];

    public function load(string $key): object|array|null
    {
        return $this->values[$key] ?? null;
    }

    public function store(string $key, object|array $value): void
    {
        $this->values[$key] = $value;
    }

    public function invalidate(string $key): void
    {
        unset($this->values[$key]);
    }
}
