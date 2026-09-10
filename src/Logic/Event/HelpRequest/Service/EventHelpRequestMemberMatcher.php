<?php

namespace App\Logic\Event\HelpRequest\Service;

use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;

/**
 * Versucht, eine Helferanmeldung automatisch einem Mitgliedsdatensatz zuzuordnen (siehe
 * `EventHelpRequest::$memberId`), damit die geleistete Arbeitszeit später dem richtigen Mitglied
 * gutgeschrieben werden kann. Um Fehlzuordnungen bei Namensgleichheit auszuschließen, wird nur bei
 * einem eindeutigen Treffer verknüpft — bleiben nach dem Eingrenzen mehrere (oder keine) Kandidaten
 * übrig, bleibt die Anmeldung unverknüpft und muss in der Verwaltung manuell verknüpft werden
 * (siehe `LinkEventHelpRequestMemberUseCase`).
 */
readonly class EventHelpRequestMemberMatcher
{
    public function __construct(private MemberManagerInterface $members)
    {
    }

    public function match(string $firstName, string $lastName, ?string $email, ?\DateTimeImmutable $birthDate): ?Member
    {
        $normalizedFirstName = mb_strtolower(trim($firstName));
        $normalizedLastName = mb_strtolower(trim($lastName));

        // Der Nachname dient nur als Vorfilter für die Datenbank-Suche (die u. a. auch im Vornamen
        // sucht) — der eigentliche Namensabgleich erfolgt anschließend exakt (lowercase+trim) auf
        // Vor- und Nachname.
        $candidates = array_values(array_filter(
            $this->members->search($lastName),
            static fn (Member $candidate): bool => mb_strtolower(trim($candidate->firstName)) === $normalizedFirstName
                && mb_strtolower(trim($candidate->lastName)) === $normalizedLastName,
        ));

        if (count($candidates) > 1 && $email !== null && trim($email) !== '') {
            $normalizedEmail = mb_strtolower(trim($email));
            $candidates = array_values(array_filter(
                $candidates,
                static fn (Member $candidate): bool => $candidate->email !== null
                    && mb_strtolower(trim($candidate->email)) === $normalizedEmail,
            ));
        }

        if (count($candidates) > 1 && $birthDate !== null) {
            $candidates = array_values(array_filter(
                $candidates,
                static fn (Member $candidate): bool => $candidate->birthDate->format('Y-m-d') === $birthDate->format('Y-m-d'),
            ));
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }
}
