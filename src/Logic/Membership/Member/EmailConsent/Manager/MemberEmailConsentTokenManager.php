<?php

namespace App\Logic\Membership\Member\EmailConsent\Manager;

use App\Logic\Membership\Member\EmailConsent\MemberEmailConsentTokenProcessorInterface;
use App\Logic\Membership\Member\EmailConsent\MemberEmailConsentTokenProviderInterface;
use App\Logic\Membership\Member\EmailConsent\Model\MemberEmailConsentToken;

readonly class MemberEmailConsentTokenManager implements MemberEmailConsentTokenManagerInterface
{
    public function __construct(
        private MemberEmailConsentTokenProviderInterface $provider,
        private MemberEmailConsentTokenProcessorInterface $processor,
    ) {
    }

    public function findByHash(string $tokenHash): ?MemberEmailConsentToken
    {
        return $this->provider->findByHash($tokenHash);
    }

    public function save(MemberEmailConsentToken $token): MemberEmailConsentToken
    {
        return $this->processor->save($token);
    }

    public function markConfirmed(string $id, \DateTimeImmutable $confirmedAt): void
    {
        $this->processor->markConfirmed($id, $confirmedAt);
    }
}
