<?php

namespace App\Logic\Membership\MemberAccess\Manager;

use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;

interface MemberAccessTokenManagerInterface
{
    public function findByHash(string $tokenHash): ?MemberAccessToken;

    public function save(MemberAccessToken $token): MemberAccessToken;

    /** Bindet alle Zugänge eines Haushalts an dessen neue Hauptnummer (Zahler-Übertragung, siehe `PayerTransfer`). */
    public function reassignHousehold(string $fromPrimaryMemberNumber, string $toPrimaryMemberNumber): void;
}
