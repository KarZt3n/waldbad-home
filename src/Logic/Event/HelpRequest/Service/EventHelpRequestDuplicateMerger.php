<?php

namespace App\Logic\Event\HelpRequest\Service;

use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;

/**
 * Stellt sicher, dass pro Mitglied und Veranstaltung höchstens eine Helferanmeldung bestehen
 * bleibt: Mehrfachanmeldungen derselben Person (z. B. weil sie sich aus Versehen zweimal angemeldet
 * hat) werden zusammengeführt, sobald sie demselben Mitglied zugeordnet sind — beim automatischen
 * Matching (siehe `SubmitEventHelpRequestUseCase`) ebenso wie beim manuellen Verknüpfen (siehe
 * `LinkEventHelpRequestMemberUseCase`). Erhalten bleibt die jüngste Anmeldung; sie übernimmt
 * Aktivitäten und erfasste Hilfezeiträume der übrigen (siehe `EventHelpRequest::mergedWith()`), die
 * danach gelöscht werden — sonst würden die Arbeitsstunden zum Jahresende nicht vollständig
 * demselben Mitglied gutgeschrieben.
 */
readonly class EventHelpRequestDuplicateMerger
{
    public function __construct(
        private EventHelpRequestManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
    ) {
    }

    /**
     * @param list<EventHelpRequest> $duplicates bereits gespeicherte Anmeldungen desselben Mitglieds
     *                                            zur selben Veranstaltung (mindestens eine)
     */
    public function merge(array $duplicates, \DateTimeImmutable $now): EventHelpRequest
    {
        if ($duplicates === []) {
            throw new \LogicException('Zum Zusammenführen wird mindestens eine Anmeldung benötigt.');
        }
        $sorted = $duplicates;
        usort($sorted, static fn (EventHelpRequest $left, EventHelpRequest $right): int => $right->submittedAt <=> $left->submittedAt);
        $survivor = array_shift($sorted);
        foreach ($sorted as $duplicate) {
            $survivor = $survivor->mergedWith($duplicate, $now, $this->identifierGenerator);
        }
        $survivor = $this->manager->save($survivor);
        foreach ($sorted as $duplicate) {
            $this->manager->delete($duplicate->id);
        }

        return $survivor;
    }

    /**
     * @param list<EventHelpRequest> $all Vorbild: `SubmitEventHelpRequestUseCase`, das für die
     *                                     Belegungszählung ebenfalls über alle Anmeldungen filtert
     * @return list<EventHelpRequest>
     */
    public function findExisting(array $all, string $memberId, string $eventIdentifier, ?string $excludingId = null): array
    {
        return array_values(array_filter(
            $all,
            static fn (EventHelpRequest $request): bool => $request->memberId === $memberId
                && $request->eventIdentifier === $eventIdentifier
                && $request->id !== $excludingId,
        ));
    }
}
