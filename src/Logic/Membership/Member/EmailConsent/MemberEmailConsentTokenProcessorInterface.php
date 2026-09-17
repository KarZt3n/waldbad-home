<?php

namespace App\Logic\Membership\Member\EmailConsent;

use App\Logic\Membership\Member\EmailConsent\Model\MemberEmailConsentToken;

interface MemberEmailConsentTokenProcessorInterface
{
    public function save(MemberEmailConsentToken $token): MemberEmailConsentToken;

    public function markConfirmed(string $id, \DateTimeImmutable $confirmedAt): void;
}
