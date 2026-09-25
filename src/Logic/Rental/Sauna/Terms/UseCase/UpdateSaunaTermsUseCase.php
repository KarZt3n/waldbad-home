<?php

namespace App\Logic\Rental\Sauna\Terms\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Rental\Sauna\Terms\Dto\SaunaTermsResponse;
use App\Logic\Rental\Sauna\Terms\Dto\UpdateSaunaTermsRequest;
use App\Logic\Rental\Sauna\Terms\Manager\SaunaTermsManagerInterface;

/**
 * Bereits eingegangene Anmeldungen behalten Personenzahl und Preis aus dem Zeitpunkt ihrer
 * Anfrage; geänderte Konditionen gelten nur für neue Anfragen.
 */
readonly class UpdateSaunaTermsUseCase
{
    public function __construct(
        private SaunaTermsManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(UpdateSaunaTermsRequest $request): SaunaTermsResponse
    {
        $terms = $this->manager->current()->revise(
            priceCents: $request->priceCents,
            priceUnitMinutes: $request->priceUnitMinutes,
            minPersons: $request->minPersons,
            maxPersons: $request->maxPersons,
            updatedAt: $this->clock->now(),
        );

        return SaunaTermsResponse::fromTerms($this->manager->save($terms));
    }
}
