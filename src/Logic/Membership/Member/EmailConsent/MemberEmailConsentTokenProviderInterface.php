<?php

namespace App\Logic\Membership\Member\EmailConsent;

use App\Logic\Membership\Member\EmailConsent\Model\MemberEmailConsentToken;

interface MemberEmailConsentTokenProviderInterface
{
    public function findByHash(string $tokenHash): ?MemberEmailConsentToken;
}
