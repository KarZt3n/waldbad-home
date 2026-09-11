<?php

namespace App\Logic\Event\HelpRequest\Service;

use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;

/**
 * Versucht, eine Helferanmeldung automatisch einem Mitgliedsdatensatz zuzuordnen (siehe
 * `EventHelpRequest::$memberId`), damit die geleistete Arbeitszeit später dem richtigen Mitglied
 * gutgeschrieben werden kann. Bleibt am Ende mehr als ein Kandidat (oder keiner) übrig, bleibt die
 * Anmeldung unverknüpft und muss in der Verwaltung manuell verknüpft werden (siehe
 * `LinkEventHelpRequestMemberUseCase`).
 *
 * Vorgehen, jede Stufe nur bei Mehrdeutigkeit der vorherigen:
 * 1. Vor-/Nachname allein (bereits eindeutig bei den allermeisten Namen).
 * 2. Vor-/Nachname + Geburtsdatum (löst Namensgleichheit mehrerer Mitglieder auf).
 * 3. Nur Vorname + Geburtsdatum bzw. nur Nachname + Geburtsdatum (löst einen Tippfehler in genau
 *    einem der beiden Namensfelder auf, sofern das Geburtsdatum stimmt).
 * 4. Bleiben danach noch mehrere Kandidaten übrig, engt die optionale E-Mail-Adresse weiter ein —
 *    geprüft gegen den ganzen Haushalt (siehe `emailBelongsToHousehold()`), nicht nur den Kandidaten
 *    selbst: Familienangehörige tragen beim Helfen häufig die E-Mail-Adresse des Hauptmitglieds ein
 *    (z. B. meldet sich "Sally Kuck" mit der E-Mail-Adresse ihres Hauptmitglieds "Karsten Kuck" an —
 *    das darf den Treffer nicht verhindern).
 */
readonly class EventHelpRequestMemberMatcher
{
    public function __construct(private MemberManagerInterface $members)
    {
    }

    public function match(string $firstName, string $lastName, \DateTimeImmutable $birthDate, ?string $email): ?Member
    {
        $normalizedFirstName = mb_strtolower(trim($firstName));
        $normalizedLastName = mb_strtolower(trim($lastName));
        $normalizedBirthDate = $birthDate->format('Y-m-d');
        $normalizedEmail = $email !== null && trim($email) !== '' ? mb_strtolower(trim($email)) : null;

        $isFirstName = static fn (Member $candidate): bool => mb_strtolower(trim($candidate->firstName)) === $normalizedFirstName;
        $isLastName = static fn (Member $candidate): bool => mb_strtolower(trim($candidate->lastName)) === $normalizedLastName;
        $isBirthDate = static fn (Member $candidate): bool => $candidate->birthDate->format('Y-m-d') === $normalizedBirthDate;

        // 1) Vor-/Nachname allein.
        $byName = $this->uniqueById(array_filter(
            $this->members->search($lastName),
            static fn (Member $candidate): bool => $isFirstName($candidate) && $isLastName($candidate),
        ));
        if (count($byName) === 1) {
            return $byName[0];
        }

        // 2) Vor-/Nachname + Geburtsdatum.
        $byNameAndBirthDate = array_values(array_filter($byName, $isBirthDate));
        if (count($byNameAndBirthDate) === 1) {
            return $byNameAndBirthDate[0];
        }

        // 3) Tippfehler in Vor- ODER Nachname: nur eines der beiden muss zusammen mit dem
        // Geburtsdatum eindeutig passen.
        $byFirstNameAndBirthDate = array_filter(
            $this->members->search($firstName),
            static fn (Member $candidate): bool => $isFirstName($candidate) && $isBirthDate($candidate),
        );
        $byLastNameAndBirthDate = array_filter(
            $this->members->search($lastName),
            static fn (Member $candidate): bool => $isLastName($candidate) && $isBirthDate($candidate),
        );
        $relaxed = $this->uniqueById([...$byFirstNameAndBirthDate, ...$byLastNameAndBirthDate]);
        if (count($relaxed) === 1) {
            return $relaxed[0];
        }

        // 4) Weiterhin mehrdeutig -> optionale E-Mail-Adresse als letztes Unterscheidungsmerkmal,
        // geprüft gegen den jeweiligen Haushalt. Der engste bereits ermittelte (aber noch
        // mehrdeutige) Kandidatenkreis wird dafür verwendet; ist der leer (Geburtsdatum passt zu
        // niemandem), wird auf den reinen Namenstreffer zurückgefallen.
        if ($normalizedEmail !== null) {
            $pool = $byNameAndBirthDate !== [] ? $byNameAndBirthDate : ($relaxed !== [] ? $relaxed : $byName);
            $viaEmail = array_values(array_filter(
                $pool,
                fn (Member $candidate): bool => $this->emailBelongsToHousehold($candidate, $normalizedEmail),
            ));
            if (count($viaEmail) === 1) {
                return $viaEmail[0];
            }
        }

        return null;
    }

    /**
     * Prüft die E-Mail-Adresse nicht nur gegen `$candidate` selbst, sondern gegen dessen gesamten
     * Haushalt (alle Mitglieder mit derselben `primaryMemberNumber`) — siehe Klassenkommentar.
     */
    private function emailBelongsToHousehold(Member $candidate, string $normalizedEmail): bool
    {
        if ($candidate->email !== null && mb_strtolower(trim($candidate->email)) === $normalizedEmail) {
            return true;
        }
        foreach ($this->members->findByPrimaryMemberNumber($candidate->primaryMemberNumber) as $householdMember) {
            if ($householdMember->email !== null && mb_strtolower(trim($householdMember->email)) === $normalizedEmail) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param iterable<Member> $candidates
     * @return list<Member>
     */
    private function uniqueById(iterable $candidates): array
    {
        $byId = [];
        foreach ($candidates as $candidate) {
            $byId[$candidate->id] = $candidate;
        }

        return array_values($byId);
    }
}
