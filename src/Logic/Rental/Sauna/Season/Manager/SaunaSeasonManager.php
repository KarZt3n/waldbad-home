<?php

namespace App\Logic\Rental\Sauna\Season\Manager;

use App\Logic\Rental\Sauna\Season\Exception\SaunaSeasonNotFoundException;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\SaunaSeasonProcessorInterface;
use App\Logic\Rental\Sauna\Season\SaunaSeasonProviderInterface;

readonly class SaunaSeasonManager implements SaunaSeasonManagerInterface
{
    public function __construct(
        private SaunaSeasonProviderInterface $provider,
        private SaunaSeasonProcessorInterface $processor,
    ) {
    }

    public function get(string $id): SaunaSeason
    {
        return $this->provider->find($id) ?? throw new SaunaSeasonNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function findCovering(\DateTimeImmutable $date): ?SaunaSeason
    {
        $covering = null;
        foreach ($this->provider->findAll() as $season) {
            if ($season->covers($date) && ($covering === null || $season->startsOn >= $covering->startsOn)) {
                $covering = $season;
            }
        }

        return $covering;
    }

    public function findCurrentOrUpcoming(\DateTimeImmutable $date): ?SaunaSeason
    {
        $current = $this->findCovering($date);
        if ($current !== null) {
            return $current;
        }

        $upcoming = null;
        foreach ($this->provider->findAll() as $season) {
            if ($season->isUpcoming($date) && ($upcoming === null || $season->startsOn < $upcoming->startsOn)) {
                $upcoming = $season;
            }
        }

        return $upcoming;
    }

    public function save(SaunaSeason $season): SaunaSeason
    {
        return $this->processor->save($season);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }
}
