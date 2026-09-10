<?php

namespace App\Logic\Membership\ContributionRate\Manager;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\ContributionRate\ContributionRateProcessorInterface;
use App\Logic\Membership\ContributionRate\ContributionRateProviderInterface;
use App\Logic\Membership\ContributionRate\Exception\ContributionRateNotFoundException;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;

/**
 * Wendet beim Lesen fällige geplante Änderungen an (siehe `ContributionRate::$pending`) — dieses
 * Projekt hat keine Scheduler-/Cronjob-Infrastruktur, daher lazy statt exakt zur Mitternacht des
 * Stichtags: die Änderung greift spätestens beim nächsten Lesezugriff (Admin-Liste, automatische
 * Beitragsermittlung, Self-Service-Anzeige). Das erreichte Datum wird zugleich als neues, für alle
 * Beitragssätze gemeinsames `ContributionRateSettings::$validFrom` gespeichert — vorgerückt, nie
 * zurückgesetzt (siehe `applyDuePendingChange()`).
 */
readonly class ContributionRateManager implements ContributionRateManagerInterface
{
    public function __construct(
        private ContributionRateProviderInterface $provider,
        private ContributionRateProcessorInterface $processor,
        private ContributionRateSettingsManagerInterface $settings,
        private ClockInterface $clock,
    ) {
    }

    public function get(string $id): ContributionRate
    {
        $rate = $this->provider->find($id) ?? throw new ContributionRateNotFoundException($id);

        return $this->applyDuePendingChange($rate);
    }

    public function findByCategory(ContributionCategory $category): ?ContributionRate
    {
        $rate = $this->provider->findByCategory($category);

        return $rate === null ? null : $this->applyDuePendingChange($rate);
    }

    public function list(): array
    {
        return array_map($this->applyDuePendingChange(...), $this->provider->findAll());
    }

    public function save(ContributionRate $rate): ContributionRate
    {
        return $this->applyDuePendingChange($this->processor->save($rate));
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }

    private function applyDuePendingChange(ContributionRate $rate): ContributionRate
    {
        $now = $this->clock->now();
        if (!$rate->isPendingChangeDue($now) || $rate->pending === null) {
            return $rate;
        }
        $pendingValidFrom = $rate->pending->validFrom;

        $applied = $this->processor->save($rate->applyPendingChange());

        $settings = $this->settings->get();
        if ($settings->validFrom === null || $pendingValidFrom > $settings->validFrom) {
            $this->settings->save($settings->withValidFrom($pendingValidFrom));
        }

        return $applied;
    }
}
