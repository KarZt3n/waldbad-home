<?php

namespace App\Logic\Content\Footer\Manager;

use App\Logic\Common\ModelCacheInterface;
use App\Logic\Content\Footer\FooterNavigationProcessorInterface;
use App\Logic\Content\Footer\FooterNavigationProviderInterface;
use App\Logic\Content\Footer\Model\FooterNavigation;

/**
 * Der Footer steht auf jeder öffentlichen Seite und wird deshalb serverseitig gecacht — auch der
 * Zustand „noch nie gespeichert“ (`null`), damit dann nicht jede Anfrage die Datenbank fragt.
 */
readonly class FooterNavigationManager implements FooterNavigationManagerInterface
{
    private const string CACHE_KEY = 'footer_navigation';

    public function __construct(
        private FooterNavigationProviderInterface $provider,
        private FooterNavigationProcessorInterface $processor,
        private ModelCacheInterface $cache,
    ) {
    }

    public function get(): ?FooterNavigation
    {
        $cached = $this->cache->load(self::CACHE_KEY);
        if (is_array($cached) && array_key_exists('navigation', $cached)
            && ($cached['navigation'] === null || $cached['navigation'] instanceof FooterNavigation)) {
            return $cached['navigation'];
        }
        $navigation = $this->provider->get();
        $this->cache->store(self::CACHE_KEY, ['navigation' => $navigation]);

        return $navigation;
    }

    public function save(FooterNavigation $navigation): FooterNavigation
    {
        $saved = $this->processor->save($navigation);
        $this->cache->invalidate(self::CACHE_KEY);

        return $saved;
    }
}
