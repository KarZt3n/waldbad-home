<?php

namespace App\Logic\Event\HelpRequest\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Service\EventHelpRequestDuplicateMerger;
use App\Logic\Event\HelpRequest\Service\EventHelpRequestRecipientResolver;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;

/**
 * Verknüpft (oder löst) eine Helferanmeldung mit einem Mitgliedsdatensatz und übernimmt dabei
 * Vor-/Nachname (in der Verwaltung die des Mitglieds) — für den Fall, dass `MemberIdentityMatcher`
 * beim Absenden keinen eindeutigen Treffer fand (z. B. wegen eines Tippfehlers) oder eine
 * bestehende Verknüpfung falsch war und korrigiert werden muss (siehe „Mitglied verknüpfen" in der
 * Helferverwaltung). Der Name gilt auch für eine dabei zusammengeführte Anmeldung.
 */
readonly class LinkEventHelpRequestMemberUseCase
{
    public function __construct(
        private EventHelpRequestManagerInterface $manager,
        private MemberManagerInterface $members,
        private EventHelpRequestDuplicateMerger $duplicateMerger,
        private EventHelpRequestRecipientResolver $recipientResolver,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id, ?string $memberId, string $firstName, string $lastName): EventHelpRequestResponse
    {
        // Wirft eine passende Exception, falls die Mitglieds-ID nicht existiert.
        $member = $memberId !== null ? $this->members->get($memberId) : null;

        $now = $this->clock->now();
        $request = $this->manager->get($id)
            ->withIdentity($firstName, $lastName, $now)
            ->withMember($memberId, $now);

        if ($memberId !== null) {
            // Verknüpft diese Anmeldung nun mit einem Mitglied, das für dieselbe Veranstaltung
            // bereits eine andere Anmeldung hat — statt zwei getrennten Datensätzen bleibt nur die
            // jüngste übrig, die beide zusammenführt (siehe `EventHelpRequestDuplicateMerger`).
            $existing = $this->duplicateMerger->findExisting($this->manager->all(), $memberId, $request->eventIdentifier, excludingId: $id);
            if ($existing !== []) {
                $merged = $this->duplicateMerger->merge([$request, ...$existing], $now);
                $request = $merged->firstName === $firstName && $merged->lastName === $lastName
                    ? $merged
                    : $this->manager->save($merged->withIdentity($firstName, $lastName, $now));
            } else {
                $request = $this->manager->save($request);
            }
        } else {
            $request = $this->manager->save($request);
        }

        return EventHelpRequestResponse::fromRequest(
            $request,
            memberNumber: $member?->memberNumber,
            memberFirstName: $member?->firstName,
            memberLastName: $member?->lastName,
            memberStreet: $member?->street,
            memberBirthDate: $member?->birthDate,
            recipientEmails: $this->recipientResolver->resolve($member, $request->email),
        );
    }
}
