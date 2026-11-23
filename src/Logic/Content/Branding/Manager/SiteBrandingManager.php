<?php

namespace App\Logic\Content\Branding\Manager;

use App\Logic\Common\ModelCacheInterface;
use App\Logic\Content\Branding\Model\SiteBranding;
use App\Logic\Content\Branding\SiteBrandingProcessorInterface;
use App\Logic\Content\Branding\SiteBrandingProviderInterface;

/** Das Logo wird mit jeder öffentlichen Seite ausgeliefert und deshalb serverseitig gecacht. */
readonly class SiteBrandingManager implements SiteBrandingManagerInterface
{
    private const string CACHE_KEY = 'site_branding';

    public function __construct(
        private SiteBrandingProviderInterface $provider,
        private SiteBrandingProcessorInterface $processor,
        private ModelCacheInterface $cache,
    ) {
    }

    public function get(): SiteBranding
    {
        $cached = $this->cache->load(self::CACHE_KEY);
        if ($cached instanceof SiteBranding) {
            return $cached;
        }
        $branding = $this->provider->get();
        $this->cache->store(self::CACHE_KEY, $branding);

        return $branding;
    }

    public function save(SiteBranding $branding): SiteBranding
    {
        $saved = $this->processor->save($branding);
        $this->cache->invalidate(self::CACHE_KEY);

        return $saved;
    }
}
