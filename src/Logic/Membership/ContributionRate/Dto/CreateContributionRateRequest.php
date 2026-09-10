<?php

namespace App\Logic\Membership\ContributionRate\Dto;

use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\PendingContributionRateChange;
use App\Logic\Membership\ContributionRate\Model\PersonGroup;
use App\Logic\Membership\PaymentInterval;

/**
 * Legt einen neuen Beitragssatz an. Ohne Kategorie (`category: null`) dient er nur der Übersicht/
 * Dokumentation (z. B. Beitrittsgebühr) und fließt nicht in die automatische Beitragsermittlung
 * ein — außer bei Zeitraum `Once` mit gesetztem Personenkreis, dann wird er automatisch bei
 * Neuanlage eines passenden Mitglieds als einmalige Gebühr berechnet. Mit einer der sechs festen
 * Kategorien kann darüber z. B. eine zuvor gelöschte Grundkategorie neu angelegt werden.
 */
readonly class CreateContributionRateRequest
{
    public function __construct(
        public ?ContributionCategory $category,
        public string $label,
        public int $amountCents,
        public PaymentInterval $period,
        public ?PersonGroup $personGroup,
        public ?int $minAge,
        public ?int $maxAge,
        public ?PendingContributionRateChange $pending,
    ) {
    }
}
