<?php

namespace App\Logic\Membership\Member;

use App\Logic\Membership\Member\Model\Member;

interface MemberProviderInterface
{
    public function find(string $id): ?Member;

    public function findByMemberNumber(string $memberNumber): ?Member;

    /**
     * Case-insensitiv (per Datenbank-Kollation, siehe `DoctrineMemberProvider`) — für „Meine
     * Mitgliedschaft“ (siehe `RequestMemberAccessUseCase`). E-Mail-Adressen sind nicht eindeutig:
     * eine Familie teilt sich in der Regel dieselbe Adresse (siehe
     * `ReleaseMembershipApplicationUseCase`), daher eine Liste statt eines einzelnen Treffers.
     *
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
}
