<?php

namespace App\Logic\Content\Footer\Manager;

use App\Logic\Content\Footer\FooterNavigationProcessorInterface;
use App\Logic\Content\Footer\FooterNavigationProviderInterface;
use App\Logic\Content\Footer\Model\FooterNavigation;

readonly class FooterNavigationManager implements FooterNavigationManagerInterface
{
    public function __construct(
        private FooterNavigationProviderInterface $provider,
        private FooterNavigationProcessorInterface $processor,
    ) {
    }

    public function get(): ?FooterNavigation
    {
        return $this->provider->get();
    }

    public function save(FooterNavigation $navigation): FooterNavigation
    {
        return $this->processor->save($navigation);
    }
}
