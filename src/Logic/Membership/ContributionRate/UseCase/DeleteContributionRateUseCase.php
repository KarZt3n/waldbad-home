<?php

namespace App\Logic\Membership\ContributionRate\UseCase;

use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;

/**
 * Löscht einen Beitragssatz — auch eine der sechs festen Grundkategorien. Fehlt eine davon zum
 * Berechnungszeitpunkt, meldet `MemberContributionCalculator` dies als fachlichen Fehler; sie
 * kann jederzeit über „Neuer Beitragssatz“ mit derselben Kategorie neu angelegt werden.
 */
readonly class DeleteContributionRateUseCase
{
    public function __construct(private ContributionRateManagerInterface $manager)
    {
    }

    public function execute(string $id): void
    {
        $this->manager->get($id);
        $this->manager->delete($id);
    }
}
