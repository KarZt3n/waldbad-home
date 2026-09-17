<?php

namespace App\Logic\Membership\Member\EmailConsent;

interface MemberEmailConsentLinkBuilderInterface
{
    public function build(string $token): string;
}
