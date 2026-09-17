<?php

namespace App\Logic\Membership\Member\EmailConsent\Manager;

use App\Logic\Membership\Member\EmailConsent\Model\MemberEmailConsentToken;

interface MemberEmailConsentTokenManagerInterface
{
    public function findByHash(string $tokenHash): ?MemberEmailConsentToken;

    public function save(MemberEmailConsentToken $token): MemberEmailConsentToken;

    public function markConfirmed(string $id, \DateTimeImmutable $confirmedAt): void;
}
