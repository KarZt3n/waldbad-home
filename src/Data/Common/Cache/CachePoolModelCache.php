<?php

namespace App\Data\Common\Cache;

use App\Logic\Common\ModelCacheInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class CachePoolModelCache implements ModelCacheInterface
{
    public function __construct(#[Autowire(service: 'cache.models')] private CacheItemPoolInterface $pool)
    {
    }

    /** @return object|array<mixed>|null */
    public function load(string $key): object|array|null
    {
        $item = $this->pool->getItem($key);
        $value = $item->isHit() ? $item->get() : null;

        return is_object($value) || is_array($value) ? $value : null;
    }

    public function store(string $key, object|array $value): void
    {
        $this->pool->save($this->pool->getItem($key)->set($value));
    }

    public function invalidate(string $key): void
    {
        $this->pool->deleteItem($key);
    }
}
