<?php

namespace App\Logic\Membership\ContributionRate\Model;

/**
 * Einzeiliger Einstellungs-Datensatz (Singleton, analog `PinSettings`): ein gemeinsames „gültig ab"
 * für **alle** Beitragssätze — anders als eine geplante Betragsänderung
 * (`ContributionRate::$pendingAmountCents`/`$pendingValidFrom`, je Beitragssatz einzeln) ist dies
 * projektweit ein einziges Datum, von Hand im Admin gepflegt oder automatisch vorgerückt, sobald
 * eine geplante Änderung greift (siehe `ContributionRateManager`).
 */
readonly class ContributionRateSettings
{
    public function __construct(
        public ?\DateTimeImmutable $validFrom,
    ) {
    }

    public function withValidFrom(?\DateTimeImmutable $validFrom): self
    {
        return new self($validFrom);
    }
}
