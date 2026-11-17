<?php

namespace App\Logic\Membership\Member\Manager;

use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberSnapshot;

interface MemberManagerInterface
{
    public function get(string $id): Member;

    public function findByMemberNumber(string $memberNumber): ?Member;

    /**
     * @return list<Member>
     */
    public function findByEmail(string $email): array;

    /**
     * @return list<Member>
     */
    public function findByPrimaryMemberNumber(string $primaryMemberNumber): array;

    /**
     * @return list<Member>
     */
    public function findByPayerMemberId(string $payerMemberId): array;

    /**
     * @return list<Member>
     */
    public function search(?string $term): array;

    /**
     * Wie `search()`, durchsucht aber nur Vor- und Nachname — jedes Wort muss in einem der beiden
     * vorkommen.
     *
     * @return list<Member>
     */
    public function searchByName(string $term): array;

    public function save(Member $member): Member;

    /**
     * Speichert alle Mitglieder in einem gemeinsamen Schreibvorgang — entweder alle oder keines.
     * Für Sammelläufe über viele Mitglieder gedacht, bei denen einzelne `save()`-Aufrufe zu
     * langsam wären.
     *
     * @param list<Member> $members
     */
    public function saveAll(array $members): void;

    /**
     * Soft-Delete: markiert das Mitglied als gelöscht, statt den Datensatz zu entfernen. Gelöschte
     * Mitglieder liefert keine der Lesemethoden mehr; ihre Mitgliedsnummer bleibt vergeben.
     */
    public function delete(string $id): void;

    /**
     * Soft-Delete mehrerer Mitglieder (siehe `delete()`) in einem gemeinsamen Schreibvorgang —
     * entweder alle oder keines.
     *
     * @param list<string> $ids
     */
    public function deleteAll(array $ids): void;

    /**
     * Alle Mitglieder einschließlich gelöschter, mit Zeitstempeln — nur für den vollständigen
     * Export und dessen Wiederherstellung.
     *
     * @return list<MemberSnapshot>
     */
    public function findAllSnapshots(): array;

    /**
     * Schreibt die Mitglieder exakt so, wie übergeben — entweder alle oder keines.
     *
     * @param list<MemberSnapshot> $snapshots
     */
    public function restoreAll(array $snapshots): void;
}
