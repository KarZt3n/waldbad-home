<?php

namespace App\Logic\Membership\MemberAccess;

use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;

interface MemberAccessTokenProcessorInterface
{
    public function save(MemberAccessToken $token): MemberAccessToken;

    /** Bindet alle Zugänge eines Haushalts an dessen neue Hauptnummer (Zahler-Übertragung, siehe `PayerTransfer`). */
    public function reassignHousehold(string $fromPrimaryMemberNumber, string $toPrimaryMemberNumber): void;
}
